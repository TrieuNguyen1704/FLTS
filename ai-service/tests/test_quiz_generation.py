from __future__ import annotations

import json
from types import SimpleNamespace

from fastapi.testclient import TestClient

import main


def source_matches() -> dict:
    return {
        'query': 'RAG',
        'embedding_model': 'gemini-embedding-2',
        'collection': 'flts_document_chunks',
        'matches': [
            {
                'vector_id': 'document:run:0',
                'content': 'RAG retrieves source passages before generation.',
                'score': 0.9,
                'document_id': 7,
                'document_name': 'chapter.pdf',
                'source_locator': 'page 3',
            },
        ],
    }


class QuizModels:
    def __init__(self, citation_index: int = 1) -> None:
        self.citation_index = citation_index

    def generate_content(self, **_: object) -> object:
        questions = [
            {
                'question': f'Question {index + 1}?',
                'options': ['A', 'B', 'C', 'D'],
                'correct_index': index % 4,
                'explanation': 'The retrieved source supports this answer.',
                'citation_indexes': [self.citation_index],
            }
            for index in range(3)
        ]
        return SimpleNamespace(text=json.dumps({'title': 'Grounded quiz', 'description': 'From course sources.', 'questions': questions}))


def test_quiz_generation_maps_source_indexes_to_authoritative_citations(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=QuizModels()))
    response = TestClient(main.app).post(
        '/internal/v1/generation/quiz',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'RAG', 'difficulty': 'medium', 'question_count': 3, 'top_k': 3},
    )
    assert response.status_code == 200, response.text
    assert len(response.json()['quiz']['questions']) == 3
    citation = response.json()['quiz']['questions'][0]['citations'][0]
    assert citation == {
        'vector_id': 'document:run:0',
        'source_locator': 'page 3',
        'document_id': 7,
        'document_name': 'chapter.pdf',
    }


def test_quiz_generation_rejects_citation_outside_retrieved_context(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    monkeypatch.setattr(main, 'search_collection', lambda _: source_matches())
    monkeypatch.setattr(main, 'gemini_client', lambda: SimpleNamespace(models=QuizModels(citation_index=2)))
    response = TestClient(main.app).post(
        '/internal/v1/generation/quiz',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'RAG', 'difficulty': 'hard', 'question_count': 3, 'top_k': 3},
    )
    assert response.status_code == 502
    assert response.json()['detail']['code'] == 'UNGROUNDABLE_CITATION'


def test_quiz_generation_validates_parameters_before_provider_call(monkeypatch) -> None:
    monkeypatch.setenv('AI_SERVICE_TOKEN', 'test-token')
    response = TestClient(main.app).post(
        '/internal/v1/generation/quiz',
        headers={'Authorization': 'Bearer test-token'},
        json={'course_id': 4, 'topic': 'x', 'difficulty': 'expert', 'question_count': 2, 'top_k': 30},
    )
    assert response.status_code == 422
