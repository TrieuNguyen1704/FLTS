from __future__ import annotations

import json
from types import SimpleNamespace

from fastapi.testclient import TestClient

import main


def source_matches() -> dict:
    return {
        'query': 'Không gian vector',
        'embedding_model': 'gemini-embedding-2',
        'collection': 'flts_document_chunks',
        'matches': [
            {
                'vector_id': f'document:run:{index}',
                'content': f'Grounded concept from source page {index + 1}.',
                'score': 0.9 - index * 0.01,
                'document_id': 7,
                'document_name': 'linear-algebra.pdf',
                'source_locator': f'page {index + 1}',
            }
            for index in range(4)
        ],
    }


class FlashcardModels:
    def __init__(self, *, citation_index: int = 1, card_count: int = 5, duplicate_fronts: bool = False) -> None:
        self.citation_index = citation_index
        self.card_count = card_count
        self.duplicate_fronts = duplicate_fronts

    def generate_content(self, **_: object) -> object:
        cards = [
            {
                'front': 'Repeated concept?' if self.duplicate_fronts else f'Concept {index + 1}?',
                'back': f'Grounded answer {index + 1}.',
                'hint': 'Recall the definition.',
                'citation_indexes': [self.citation_index],
            }
            for index in range(self.card_count)
        ]
        return SimpleNamespace(text=json.dumps({
            'title': 'Vector spaces',
            'description': 'Grounded review cards.',
            'cards': cards,
        }))


def test_flashcard_generation_maps_authoritative_citations_and_limits_pages(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=FlashcardModels()))
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-flashcards',
        headers={'Authorization': 'Bearer test-token'},
        json={
            'course_id': 4, 'topic': 'Không gian vector', 'difficulty': 'medium',
            'card_count': 5, 'top_k': 8, 'max_pages': 2,
        },
    )
    assert response.status_code == 200, response.text
    payload = response.json()
    assert len(payload['flashcards']['cards']) == 5
    assert payload['retrieval']['match_count'] == 2
    assert payload['retrieval']['max_pages'] == 2
    assert payload['flashcards']['cards'][0]['citations'][0] == {
        'vector_id': 'document:run:0',
        'source_locator': 'page 1',
        'document_id': 7,
        'document_name': 'linear-algebra.pdf',
    }


def test_flashcard_generation_rejects_citation_outside_limited_context(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=FlashcardModels(citation_index=3)))
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-flashcards',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'Vector space', 'card_count': 5, 'top_k': 8, 'max_pages': 2},
    )
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'UNGROUNDABLE_CITATION'


def test_flashcard_generation_rejects_duplicate_cards(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=FlashcardModels(duplicate_fronts=True)))
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-flashcards',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'Vector space', 'card_count': 5, 'top_k': 8, 'max_pages': 3},
    )
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'FLASHCARD_SCHEMA_INVALID'


def test_flashcard_generation_validates_parameters_before_provider_call(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    response = TestClient(main.app).post(
        '/internal/v1/learning-objects/generate-flashcards',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'x', 'difficulty': 'expert', 'card_count': 2, 'top_k': 30, 'max_pages': 20},
    )
    assert response.status_code == 422
