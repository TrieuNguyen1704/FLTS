from __future__ import annotations

import json
import threading
from types import SimpleNamespace

import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient

import main
from rag_pipeline import PipelineError


def text_pdf(value: str) -> bytes:
    stream = f'BT /F1 18 Tf 72 72 Td ({value}) Tj ET'
    objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        f'<< /Length {len(stream)} >>\nstream\n{stream}\nendstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ]
    data = b'%PDF-1.4\n'
    offsets = [0]
    for index, body in enumerate(objects, start=1):
        offsets.append(len(data))
        data += f'{index} 0 obj\n{body}\nendobj\n'.encode()
    xref = len(data)
    data += f'xref\n0 {len(objects) + 1}\n0000000000 65535 f \n'.encode()
    data += b''.join(f'{offset:010d} 00000 n \n'.encode() for offset in offsets[1:])
    return data + f'trailer\n<< /Size {len(objects) + 1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF'.encode()


def headers(monkeypatch) -> dict[str, str]:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    return {'Authorization': 'Bearer test-token'}


def test_missing_service_token_never_falls_back_to_a_predictable_default(monkeypatch) -> None:
    monkeypatch.delenv('AI_SERVICE_TOKEN', raising=False)
    with pytest.raises(HTTPException) as error:
        main.require_service_token('Bearer guessed-example-token')
    assert error.value.status_code == 401


def test_document_processing_does_not_block_health_requests(monkeypatch) -> None:
    started = threading.Event()
    release = threading.Event()

    def slow_processing(*_: object) -> dict:
        started.set()
        release.wait(timeout=3)
        return {'extraction': {}, 'chunks': [], 'embedding_model': 'test', 'vector_store': {}}

    monkeypatch.setattr(main, 'process_document_content', slow_processing)
    client = TestClient(main.app)
    auth_headers = headers(monkeypatch)
    response_holder: list[object] = []

    thread = threading.Thread(target=lambda: response_holder.append(client.post(
        '/internal/v1/documents/process', headers=auth_headers,
        data={'document_id': '1', 'course_id': '1', 'processing_run_id': '1', 'extension': 'pdf', 'mime_type': 'application/pdf'},
        files={'file': ('chapter.pdf', b'content', 'application/pdf')},
    )))
    thread.start()
    assert started.wait(timeout=1)
    assert client.get('/health').status_code == 200
    release.set()
    thread.join(timeout=2)
    assert response_holder[0].status_code == 200


def test_retry_delay_parses_and_bounds_provider_hints() -> None:
    assert main.extract_retry_delay('retry in 4.5s') == 15.0
    assert main.extract_retry_delay('retryDelay: 42') == 44.0
    assert main.extract_retry_delay('retry in 120s') == 60.0
    assert main.extract_retry_delay('no provider hint') == 35.0


class FakeEmbeddingModels:
    def __init__(self, fail_first: bool = False) -> None:
        self.calls: list[list[object]] = []
        self.fail_first = fail_first
        self.next_value = 0

    def embed_content(self, *, contents: list[object], **_: object) -> object:
        self.calls.append(contents)
        if self.fail_first and len(self.calls) == 1:
            raise RuntimeError('429 RESOURCE_EXHAUSTED: retry in 0.5s')
        embeddings = []
        for _ in contents:
            self.next_value += 1
            embeddings.append(SimpleNamespace(values=[float(self.next_value)] + [0.0] * 767))
        return SimpleNamespace(embeddings=embeddings)


class FakeEmbeddingClient:
    def __init__(self, models: FakeEmbeddingModels) -> None:
        self.models = models


def test_embed_batches_contents_and_preserves_vector_order(monkeypatch) -> None:
    models = FakeEmbeddingModels()
    sleep_calls: list[float] = []
    monkeypatch.setattr(main, 'gemini_client', lambda: FakeEmbeddingClient(models))
    monkeypatch.setattr(main, 'EMBEDDING_MODEL', 'gemini-embedding-2')
    monkeypatch.setattr(main, 'EMBEDDING_BATCH_SIZE', 40)
    monkeypatch.setattr(main, 'EMBEDDING_MAX_RETRIES', 2)
    monkeypatch.setattr(main.time, 'sleep', sleep_calls.append)

    vectors = main.embed([f'chunk {index}' for index in range(81)], 'RETRIEVAL_DOCUMENT')

    assert [len(batch) for batch in models.calls] == [40, 40, 1]
    assert len(vectors) == 81
    assert [vector[0] for vector in vectors] == [float(index) for index in range(1, 82)]
    assert sleep_calls == [1.0, 1.0, 1.0]


def test_embed_retries_a_rate_limited_batch_without_leaking_provider_message(monkeypatch) -> None:
    models = FakeEmbeddingModels(fail_first=True)
    sleep_calls: list[float] = []
    monkeypatch.setattr(main, 'gemini_client', lambda: FakeEmbeddingClient(models))
    monkeypatch.setattr(main, 'EMBEDDING_BATCH_SIZE', 40)
    monkeypatch.setattr(main, 'EMBEDDING_QUERY_MAX_RETRIES', 2)
    monkeypatch.setattr(main.time, 'sleep', sleep_calls.append)

    vectors = main.embed(['one chunk'], 'RETRIEVAL_QUERY')

    assert len(models.calls) == 2
    assert len(vectors) == 1
    assert sleep_calls == [15.0, 1.0]


def test_interactive_query_fails_fast_when_gemini_is_rate_limited(monkeypatch) -> None:
    models = FakeEmbeddingModels(fail_first=True)
    sleep_calls: list[float] = []
    monkeypatch.setattr(main, 'gemini_client', lambda: FakeEmbeddingClient(models))
    monkeypatch.setattr(main, 'EMBEDDING_QUERY_MAX_RETRIES', 1)
    monkeypatch.setattr(main.time, 'sleep', sleep_calls.append)

    with pytest.raises(PipelineError) as error:
        main.embed(['one query'], 'RETRIEVAL_QUERY')

    assert error.value.code == 'GEMINI_RATE_LIMITED'
    assert error.value.status_code == 429
    assert sleep_calls == []


def test_embed_hides_unexpected_provider_diagnostics(monkeypatch) -> None:
    class BrokenModels:
        def embed_content(self, **_: object) -> object:
            raise RuntimeError('provider diagnostic that must not leave the AI service')

    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=BrokenModels()))
    monkeypatch.setattr(main, 'EMBEDDING_MAX_RETRIES', 1)
    with pytest.raises(PipelineError) as error:
        main.embed(['one chunk'], 'RETRIEVAL_QUERY')

    assert error.value.code == 'EMBEDDING_FAILED'
    assert 'provider diagnostic' not in str(error.value)


def fake_embeddings(contents: list[str], _: str) -> list[list[float]]:
    # This test proves the real Chroma contract without presenting a local fake as a Gemini verification.
    return [[float(index + 1)] + [0.0] * 767 for index, _ in enumerate(contents)]


class FakeGeneratedResponse:
    def __init__(self, payload: dict) -> None:
        self.text = json.dumps(payload)


class FakeGeminiModels:
    def generate_content(self, **_: object) -> FakeGeneratedResponse:
        return FakeGeneratedResponse({
            'answer': 'Grounded answer from the selected course source.',
            'citation_indexes': [1],
            'limitations': 'Only the retrieved source was used.',
        })


class FakeGeminiClient:
    models = FakeGeminiModels()


def process(client: TestClient, auth_headers: dict[str, str], document_id: int, course_id: int, text: str) -> None:
    response = client.post(
        '/internal/v1/documents/process', headers=auth_headers,
        data={'document_id': str(document_id), 'course_id': str(course_id), 'processing_run_id': '1', 'extension': 'pdf', 'mime_type': 'application/pdf'},
        files={'file': ('chapter.pdf', text_pdf(text), 'application/pdf')},
    )
    assert response.status_code == 200, response.text


def test_process_retrieve_filter_and_delete_use_real_chromadb(monkeypatch) -> None:
    monkeypatch.setattr(main, 'embed', fake_embeddings)
    monkeypatch.setattr(main, 'gemini_client', FakeGeminiClient)
    client = TestClient(main.app)
    auth_headers = headers(monkeypatch)

    process(client, auth_headers, document_id=990101, course_id=9901, text='Course one RAG source owner@flts.test')
    process(client, auth_headers, document_id=990201, course_id=9902, text='Course two private source')

    response = client.post('/internal/v1/retrieval/search', headers=auth_headers, json={'course_id': 9901, 'query': 'RAG source', 'top_k': 5})
    assert response.status_code == 200, response.text
    matches = response.json()['matches']
    assert [match['document_id'] for match in matches] == [990101]
    assert matches[0]['source_locator'] == 'page 1'
    assert 'owner@flts.test' not in matches[0]['content']

    evidence = client.post('/internal/v1/evidence/generate', headers=auth_headers, json={'course_id': 9901, 'prompt': 'What does the source say?', 'top_k': 5})
    assert evidence.status_code == 200, evidence.text
    assert evidence.json()['evidence']['citations'][0]['vector_id'] == '990101:1:0'

    deleted = client.delete('/internal/v1/documents/990101/vectors', headers=auth_headers)
    assert deleted.status_code == 200
    after_delete = client.post('/internal/v1/retrieval/search', headers=auth_headers, json={'course_id': 9901, 'query': 'RAG source', 'top_k': 5})
    assert after_delete.status_code == 200
    assert after_delete.json()['matches'] == []

    # Course cleanup removes every vector owned by the course and keeps the shared test collection clean.
    deleted_course = client.delete('/internal/v1/courses/9902/vectors', headers=auth_headers)
    assert deleted_course.status_code == 200
    after_course_delete = client.post('/internal/v1/retrieval/search', headers=auth_headers, json={'course_id': 9902, 'query': 'private source', 'top_k': 5})
    assert after_course_delete.status_code == 200
    assert after_course_delete.json()['matches'] == []
