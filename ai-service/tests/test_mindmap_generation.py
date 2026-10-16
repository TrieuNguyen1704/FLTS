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


class MindmapModels:
    def __init__(self, citation_index: int = 1, too_deep: bool = False) -> None:
        self.citation_index = citation_index
        self.too_deep = too_deep

    def generate_content(self, **_: object) -> object:
        leaf = {'label': 'Phép cộng $u+v$', 'citation_indexes': [self.citation_index], 'children': []}
        if self.too_deep:
            leaf = {'label': 'Cấp bốn', 'citation_indexes': [1], 'children': [leaf]}
        return SimpleNamespace(text=json.dumps({
            'title': 'Không gian vector', 'description': 'Sơ đồ có căn cứ.',
            'root': {'label': 'Không gian vector', 'citation_indexes': [1], 'children': [
                {'label': 'Định nghĩa', 'citation_indexes': [1], 'children': [leaf]},
                {'label': 'Tính chất', 'citation_indexes': [2], 'children': []},
            ]},
        }))


def test_mindmap_maps_authoritative_citations_ids_and_limits_pages(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=MindmapModels()))
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-mindmap',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'Không gian vector', 'depth': 3, 'top_k': 8, 'max_pages': 2},
    )
    assert response.status_code == 200, response.text
    payload = response.json()
    root = payload['mindmap']['root']
    assert root['id'] == 'root'
    assert root['children'][0]['id'] == 'node_1'
    assert root['children'][0]['children'][0]['id'] == 'node_1_1'
    assert root['children'][0]['citations'][0] == {
        'vector_id': '7:14:0', 'source_locator': 'page 1',
        'document_id': 7, 'document_name': 'linear-algebra.pdf',
    }
    assert payload['retrieval']['match_count'] == 2
    assert payload['mindmap']['markdown'].startswith('# Không gian vector')


def test_mindmap_rejects_untrusted_citation_and_excess_depth(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=MindmapModels(citation_index=3)))
    client = TestClient(main.app)
    response = client.post('/internal/v1/learning-objects/generate-mindmap', headers={'Authorization': 'Bearer test-token'}, json={
        'course_id': 4, 'topic': 'Vector space', 'depth': 3, 'max_pages': 2,
    })
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'UNGROUNDABLE_CITATION'

    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=MindmapModels(too_deep=True)))
    response = client.post('/internal/v1/learning-objects/generate-mindmap', headers={'Authorization': 'Bearer test-token'}, json={
        'course_id': 4, 'topic': 'Vector space', 'depth': 2, 'max_pages': 3,
    })
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'MINDMAP_SCHEMA_INVALID'


def test_mindmap_validates_parameters_before_provider_call(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-mindmap', headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'x', 'depth': 8, 'max_pages': 20},
    )
    assert response.status_code == 422


def test_mindmap_null_child_placeholders_are_removed_before_validation() -> None:
    cleaned = main.clean_mindmap_draft_node({
        'label': 'Root', 'citation_indexes': [1],
        'children': [None, {'label': 'Child', 'citation_indexes': [1], 'children': [None]}],
    })
    assert len(cleaned['children']) == 1
    assert cleaned['children'][0]['children'] == []
