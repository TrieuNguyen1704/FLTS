from __future__ import annotations

import json

from fastapi.testclient import TestClient

import main


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
            'citations': [{'vector_id': '990101:1:0', 'source_locator': 'page 1'}],
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

    process(client, auth_headers, document_id=990101, course_id=9901, text='Course one RAG source')
    process(client, auth_headers, document_id=990201, course_id=9902, text='Course two private source')

    response = client.post('/internal/v1/retrieval/search', headers=auth_headers, json={'course_id': 9901, 'query': 'RAG source', 'top_k': 5})
    assert response.status_code == 200, response.text
    matches = response.json()['matches']
    assert [match['document_id'] for match in matches] == [990101]
    assert matches[0]['source_locator'] == 'page 1'

    evidence = client.post('/internal/v1/evidence/generate', headers=auth_headers, json={'course_id': 9901, 'prompt': 'What does the source say?', 'top_k': 5})
    assert evidence.status_code == 200, evidence.text
    assert evidence.json()['evidence']['citations'][0]['vector_id'] == '990101:1:0'

    deleted = client.delete('/internal/v1/documents/990101/vectors', headers=auth_headers)
    assert deleted.status_code == 200
    after_delete = client.post('/internal/v1/retrieval/search', headers=auth_headers, json={'course_id': 9901, 'query': 'RAG source', 'top_k': 5})
    assert after_delete.status_code == 200
    assert after_delete.json()['matches'] == []

    # Keep the shared local Chroma volume clean after this integration test.
    client.delete('/internal/v1/documents/990201/vectors', headers=auth_headers)
