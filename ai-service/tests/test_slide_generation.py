from __future__ import annotations

import json
from types import SimpleNamespace

from fastapi.testclient import TestClient

import main


def source_matches() -> dict:
    return {
        'query': 'Không gian vector', 'embedding_model': 'gemini-embedding-2',
        'collection': 'flts_document_chunks',
        'matches': [{
            'vector_id': f'7:14:{index}', 'content': f'Grounded page {index + 1}.',
            'score': .9, 'document_id': 7, 'document_name': 'linear-algebra.pdf',
            'source_locator': f'page {index + 1}',
        } for index in range(4)],
    }


class SlideModels:
    def __init__(self, citation_index: int = 1, slide_count: int = 5) -> None:
        self.citation_index = citation_index
        self.slide_count = slide_count

    def generate_content(self, **_: object) -> object:
        slides = [{
            'title': f'Slide {index + 1}',
            'layout': 'title' if index == 0 else 'summary' if index == self.slide_count - 1 else 'bullet_points',
            'bullet_points': ['Ý chính một', 'Ý chính hai', 'Công thức $u+v$'],
            'speaker_notes': 'Giải thích khái niệm dựa trên giáo trình.',
            'citation_indexes': [self.citation_index],
        } for index in range(self.slide_count)]
        return SimpleNamespace(text=json.dumps({'title': 'Không gian vector', 'description': 'Bài giảng có căn cứ.', 'slides': slides}))


def test_slides_map_authoritative_citations_and_limit_pages(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=SlideModels()))
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-slides', headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'Không gian vector', 'slide_count': 5, 'top_k': 8, 'max_pages': 2},
    )
    assert response.status_code == 200, response.text
    payload = response.json()
    assert len(payload['slides']['slides']) == 5
    assert payload['slides']['slides'][0]['slide_number'] == 1
    assert payload['slides']['slides'][0]['citations'][0]['vector_id'] == '7:14:0'
    assert payload['retrieval']['match_count'] == 2


def test_slides_reject_invalid_citation_and_count(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    client = TestClient(main.app)
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=SlideModels(citation_index=3)))
    response = client.post('/internal/v1/learning-objects/generate-slides', headers={'Authorization': 'Bearer test-token'}, json={
        'course_id': 4, 'topic': 'Vector space', 'slide_count': 5, 'max_pages': 2,
    })
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'UNGROUNDABLE_CITATION'

    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=SlideModels(slide_count=6)))
    response = client.post('/internal/v1/learning-objects/generate-slides', headers={'Authorization': 'Bearer test-token'}, json={
        'course_id': 4, 'topic': 'Vector space', 'slide_count': 5, 'max_pages': 3,
    })
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'SLIDE_SCHEMA_INVALID'


def test_slides_validate_parameters_before_provider_call(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-slides', headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'x', 'slide_count': 20, 'max_pages': 20},
    )
    assert response.status_code == 422
