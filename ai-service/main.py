from __future__ import annotations

import json
import logging
import os
import re
import time
from typing import Annotated, Any, Literal

import chromadb
from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from google import genai
from google.genai import types
from pydantic import BaseModel, Field
from starlette.concurrency import run_in_threadpool

from rag_pipeline import PipelineError, chunk_text, extract_document

APP = FastAPI(title='FLTS AI Service', version='0.2.0')
# Keep the conventional ASGI export expected by the Docker uvicorn command.
app = APP
# Uvicorn configures this logger in the container, so provider failures are retained for operators without reaching browsers.
LOGGER = logging.getLogger('uvicorn.error')
COLLECTION_NAME = os.getenv('CHROMA_COLLECTION', 'flts_document_chunks')
EMBEDDING_MODEL = os.getenv('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-2')
GENERATION_MODEL = os.getenv('GEMINI_GENERATION_MODEL', 'gemini-2.5-flash')
EMBEDDING_BATCH_SIZE = int(os.getenv('GEMINI_EMBEDDING_BATCH_SIZE', '40'))
EMBEDDING_MAX_RETRIES = int(os.getenv('GEMINI_EMBEDDING_MAX_RETRIES', '15'))
# Interactive retrieval/evidence must respond promptly. Long retries are reserved for the queue worker.
EMBEDDING_QUERY_MAX_RETRIES = int(os.getenv('GEMINI_QUERY_MAX_RETRIES', '1'))


@APP.exception_handler(PipelineError)
async def pipeline_error_handler(_, exc: PipelineError) -> JSONResponse:
    LOGGER.warning('RAG pipeline error code=%s stage=%s status=%s', exc.code, exc.stage, exc.status_code)
    return JSONResponse(status_code=exc.status_code, content={'detail': {'code': exc.code, 'message': str(exc), 'stage': exc.stage}})


def require_service_token(authorization: Annotated[str | None, Header()] = None) -> None:
    expected = os.getenv('AI_SERVICE_TOKEN', '')
    if not expected or authorization != f'Bearer {expected}':
        raise HTTPException(status_code=401, detail={'code': 'UNAUTHORIZED_SERVICE', 'message': 'A valid internal service token is required.', 'stage': 'authorization'})


def gemini_client() -> genai.Client:
    api_key = os.getenv('GEMINI_API_KEY', '')
    if not api_key:
        raise PipelineError('GEMINI_NOT_CONFIGURED', 'GEMINI_API_KEY is not configured for the local AI service.', 'embedding', 503)
    return genai.Client(api_key=api_key)


def chroma_collection():
    try:
        client = chromadb.HttpClient(host=os.getenv('CHROMA_HOST', 'chroma'), port=int(os.getenv('CHROMA_PORT', '8000')))
        return client.get_or_create_collection(name=COLLECTION_NAME, metadata={'hnsw:space': 'cosine'})
    except Exception as exc:
        raise PipelineError('VECTOR_STORE_UNAVAILABLE', 'ChromaDB is not available.', 'vectorizing', 503) from exc


def extract_retry_delay(err_str: str) -> float:
    match = re.search(r'retry in\s+([0-9.]+)\s*s', err_str, re.IGNORECASE)
    if match:
        try:
            return min(60.0, max(15.0, float(match.group(1)) + 2.0))
        except ValueError:
            pass
    match_delay = re.search(r'retryDelay[\'":\s]+([0-9.]+)', err_str, re.IGNORECASE)
    if match_delay:
        try:
            return min(60.0, max(15.0, float(match_delay.group(1)) + 2.0))
        except ValueError:
            pass
    return 35.0


def is_rate_limited(exception: Exception) -> bool:
    message = str(exception)
    return 'RESOURCE_EXHAUSTED' in message or '429' in message


def embed(contents: list[str], task_type: str) -> list[list[float]]:
    if not contents:
        return []
    config_kwargs: dict[str, Any] = {'task_type': task_type}
    if 'gemini-embedding' in EMBEDDING_MODEL:
        config_kwargs['output_dimensionality'] = 768
    client = gemini_client()
    vectors: list[list[float]] = []
    batch_size = EMBEDDING_BATCH_SIZE
    total_batches = (len(contents) + batch_size - 1) // batch_size
    try:
        for idx in range(0, len(contents), batch_size):
            batch = contents[idx:idx + batch_size]
            batch_num = idx // batch_size + 1
            print(f'Embedding batch {batch_num}/{total_batches} ({len(batch)} items)...', flush=True)
            formatted_contents = [types.Content(parts=[types.Part.from_text(text=item)]) for item in batch]
            max_retries = EMBEDDING_QUERY_MAX_RETRIES if task_type == 'RETRIEVAL_QUERY' else EMBEDDING_MAX_RETRIES
            for attempt in range(max_retries):
                try:
                    response = client.models.embed_content(
                        model=EMBEDDING_MODEL,
                        contents=formatted_contents,
                        config=types.EmbedContentConfig(**config_kwargs),
                    )
                    for item in response.embeddings:
                        vectors.append(list(item.values))
                    time.sleep(1.0)
                    break
                except Exception as exc:
                    err_str = str(exc)
                    if is_rate_limited(exc):
                        if attempt + 1 >= max_retries:
                            raise PipelineError(
                                'GEMINI_RATE_LIMITED',
                                'Gemini is temporarily rate limited. Please retry shortly.',
                                'embedding',
                                429,
                            ) from exc
                        delay = extract_retry_delay(err_str)
                        print(f'Rate limit reached on batch {batch_num} (attempt {attempt + 1}/{max_retries}). Waiting {delay:.1f}s...', flush=True)
                        time.sleep(delay)
                    else:
                        raise
            else:
                raise RuntimeError(f'Failed to embed batch {batch_num} after {max_retries} attempts.')
    except PipelineError:
        raise
    except Exception as exc:
        # Keep provider diagnostics in internal logs instead of returning them to Laravel for persistence.
        LOGGER.exception('Gemini embedding request failed')
        raise PipelineError('EMBEDDING_FAILED', 'Gemini could not create document embeddings.', 'embedding', 502) from exc
    if len(vectors) != len(contents) or any(len(vector) != 768 for vector in vectors):
        raise PipelineError('EMBEDDING_DIMENSION_INVALID', 'Gemini did not return the expected 768-dimensional embeddings.', 'embedding', 502)
    return vectors


class SearchRequest(BaseModel):
    course_id: int
    query: str = Field(min_length=3, max_length=2000)
    top_k: int = Field(default=5, ge=1, le=15)
    document_ids: list[int] = Field(default_factory=list)


class EvidenceRequest(BaseModel):
    course_id: int
    prompt: str = Field(min_length=3, max_length=2000)
    top_k: int = Field(default=5, ge=1, le=10)


class Citation(BaseModel):
    vector_id: str
    source_locator: str | None = None


class EvidenceOutput(BaseModel):
    answer: str
    citations: list[Citation]
    limitations: str


class EvidenceDraft(BaseModel):
    answer: str
    citation_indexes: list[int] = Field(min_length=1)
    limitations: str


class QuizGenerationRequest(BaseModel):
    course_id: int
    topic: str = Field(min_length=3, max_length=500)
    difficulty: Literal['easy', 'medium', 'hard'] = 'medium'
    question_count: int = Field(default=5, ge=3, le=20)
    document_ids: list[int] = Field(default_factory=list)
    top_k: int = Field(default=8, ge=3, le=15)


class QuizQuestionDraft(BaseModel):
    question: str = Field(min_length=3, max_length=2000)
    options: list[str] = Field(min_length=4, max_length=4)
    correct_index: int = Field(ge=0, le=3)
    explanation: str = Field(min_length=3, max_length=4000)
    citation_indexes: list[int] = Field(min_length=1)


class QuizDraft(BaseModel):
    title: str = Field(min_length=3, max_length=255)
    description: str = Field(default='', max_length=2000)
    questions: list[QuizQuestionDraft] = Field(min_length=3, max_length=20)


class QuizCitation(BaseModel):
    vector_id: str
    source_locator: str | None = None
    document_id: int
    document_name: str


class QuizQuestionOutput(BaseModel):
    question: str
    options: list[str]
    correct_index: int
    explanation: str
    citations: list[QuizCitation]


class QuizOutput(BaseModel):
    title: str
    description: str
    questions: list[QuizQuestionOutput]


def redact_sensitive_text(value: str) -> str:
    return re.sub(r'(?i)\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b', '[redacted-email]', value)


def search_collection(request: SearchRequest) -> dict[str, Any]:
    vector = embed([request.query], 'RETRIEVAL_QUERY')[0]
    where: dict[str, Any] = {'course_id': str(request.course_id)}
    if request.document_ids:
        where = {'$and': [where, {'document_id': {'$in': [str(value) for value in request.document_ids]}}]}
    try:
        result = chroma_collection().query(query_embeddings=[vector], n_results=request.top_k, where=where, include=['documents', 'metadatas', 'distances'])
    except PipelineError:
        raise
    except Exception as exc:
        raise PipelineError('RETRIEVAL_FAILED', 'ChromaDB could not search the processed chunks.', 'retrieval', 503) from exc
    ids, documents, metadatas, distances = (result.get('ids', [[]])[0], result.get('documents', [[]])[0], result.get('metadatas', [[]])[0], result.get('distances', [[]])[0])
    matches = []
    for vector_id, content, metadata, distance in zip(ids, documents, metadatas, distances):
        matches.append({
            'vector_id': vector_id, 'content': redact_sensitive_text(content), 'score': round(1 / (1 + float(distance)), 4),
            'document_id': int(metadata['document_id']), 'document_name': metadata['document_name'],
            'source_locator': metadata.get('source_locator') or None,
        })
    return {'query': request.query, 'matches': matches, 'embedding_model': EMBEDDING_MODEL, 'collection': COLLECTION_NAME}


@APP.get('/health')
def health() -> dict[str, Any]:
    return {
        'status': 'ok', 'service': 'fastapi-rag', 'embedding_model': EMBEDDING_MODEL,
        'generation_model': GENERATION_MODEL, 'vector_store': 'chromadb',
        'gemini_configured': bool(os.getenv('GEMINI_API_KEY')),
    }


@APP.post('/internal/v1/documents/process', dependencies=[Depends(require_service_token)])
async def process_document(
    file: Annotated[UploadFile, File()],
    document_id: Annotated[str, Form()],
    course_id: Annotated[str, Form()],
    processing_run_id: Annotated[str, Form()],
    extension: Annotated[str, Form()],
    mime_type: Annotated[str, Form()],
    max_pages: Annotated[int | None, Form()] = None,
) -> dict[str, Any]:
    content = await file.read()
    # Parsing, provider retries and Chroma writes are synchronous. Running them in FastAPI's
    # worker pool keeps health checks and interactive quiz requests responsive.
    return await run_in_threadpool(
        process_document_content,
        content,
        file.filename or 'document',
        document_id,
        course_id,
        processing_run_id,
        extension,
        mime_type,
        max_pages,
    )


def process_document_content(
    content: bytes,
    filename: str,
    document_id: str,
    course_id: str,
    processing_run_id: str,
    extension: str,
    mime_type: str,
    max_pages: int | None = None,
) -> dict[str, Any]:
    extracted = extract_document(content, extension, max_pages=max_pages)
    chunks = chunk_text(extracted.text)
    if not chunks:
        raise PipelineError('NO_CHUNKS_CREATED', 'No chunks could be created from the extracted text.', 'chunking')
    vectors = embed([chunk['content'] for chunk in chunks], 'RETRIEVAL_DOCUMENT')
    collection = chroma_collection()
    # Reprocessing a document replaces its searchable representation rather than mixing old and new chunks.
    try:
        collection.delete(where={'document_id': str(document_id)})
        vector_ids = [f'{document_id}:{processing_run_id}:{chunk["chunk_index"]}' for chunk in chunks]
        metadatas = [{
            'course_id': str(course_id), 'document_id': str(document_id), 'document_name': filename,
            'source_locator': chunk['source_locator'] or '', 'chunk_index': chunk['chunk_index'], 'mime_type': mime_type,
        } for chunk in chunks]
        collection.upsert(ids=vector_ids, documents=[chunk['content'] for chunk in chunks], embeddings=vectors, metadatas=metadatas)
    except Exception as exc:
        raise PipelineError('VECTOR_UPSERT_FAILED', 'ChromaDB could not store the document chunks.', 'vectorizing', 503) from exc

    response_chunks = []
    for chunk, vector_id in zip(chunks, vector_ids):
        response_chunks.append({**chunk, 'vector_id': vector_id, 'dimensions': 768})
    return {
        'extraction': {'normalized_text': extracted.text, 'character_count': len(extracted.text), 'page_count': extracted.page_count, 'metadata': extracted.metadata},
        'chunks': response_chunks,
        'embedding_model': EMBEDDING_MODEL,
        'vector_store': {'provider': 'chromadb', 'collection': COLLECTION_NAME},
    }


@APP.post('/internal/v1/retrieval/search', dependencies=[Depends(require_service_token)])
def retrieval_search(request: SearchRequest) -> dict[str, Any]:
    return search_collection(request)


@APP.post('/internal/v1/evidence/generate', dependencies=[Depends(require_service_token)])
def generate_evidence(request: EvidenceRequest) -> dict[str, Any]:
    retrieved = search_collection(SearchRequest(course_id=request.course_id, query=request.prompt, top_k=request.top_k))
    matches = retrieved['matches']
    if not matches:
        raise PipelineError('NO_GROUNDED_CONTEXT', 'No processed chunks matched this request.', 'generation', 422)
    context = '\n\n'.join(f'[Source {index}] {match["content"]}' for index, match in enumerate(matches, start=1))
    instruction = (
        'Answer only from the source chunks below. Return Vietnamese JSON following the schema. '
        'citation_indexes must contain one or more supplied Source numbers and must not invent any number. '\
        f'\n\nQuestion: {request.prompt}\n\nSource chunks:\n{context}'
    )
    try:
        client = gemini_client()
        response = client.models.generate_content(
            model=GENERATION_MODEL,
            contents=instruction,
            # google-genai 2.x calls this response_schema; it constrains the provider output before validation below.
            config=types.GenerateContentConfig(response_mime_type='application/json', response_schema=EvidenceDraft.model_json_schema()),
        )
        draft = EvidenceDraft.model_validate(json.loads(response.text))
    except PipelineError:
        raise
    except Exception as exc:
        print(f'Gemini evidence-generation failure type: {type(exc).__name__}', flush=True)
        LOGGER.exception('Gemini evidence-generation request failed')
        if is_rate_limited(exc):
            raise PipelineError('GEMINI_RATE_LIMITED', 'Gemini is temporarily rate limited. Please retry shortly.', 'generation', 429) from exc
        raise PipelineError('GENERATION_FAILED', 'Gemini could not generate grounded evidence.', 'generation', 502) from exc
    if any(index < 1 or index > len(matches) for index in draft.citation_indexes):
        raise PipelineError('UNGROUNDABLE_CITATION', 'Gemini returned a citation outside the retrieved source set.', 'generation', 502)
    selected_indexes = list(dict.fromkeys(draft.citation_indexes))
    output = EvidenceOutput(
        answer=draft.answer,
        limitations=draft.limitations,
        citations=[Citation(vector_id=matches[index - 1]['vector_id'], source_locator=matches[index - 1]['source_locator']) for index in selected_indexes],
    )
    return {'evidence': output.model_dump(), 'retrieval': retrieved}


@APP.post('/internal/v1/generation/quiz', dependencies=[Depends(require_service_token)])
def generate_quiz(request: QuizGenerationRequest) -> dict[str, Any]:
    retrieved = search_collection(SearchRequest(
        course_id=request.course_id,
        query=request.topic,
        top_k=request.top_k,
        document_ids=request.document_ids,
    ))
    matches = retrieved['matches']
    if not matches:
        raise PipelineError('NO_GROUNDED_CONTEXT', 'No processed chunks matched this quiz topic.', 'generation', 422)
    context = '\n\n'.join(f'[Source {index}] {match["content"]}' for index, match in enumerate(matches, start=1))
    instruction = (
        'Create a Vietnamese single-choice educational quiz using only the numbered source chunks below.\n'
        f'- Topic: {request.topic}\n'
        f'- Question count: {request.question_count} | Difficulty: {request.difficulty}\n\n'
        'REQUIREMENTS:\n'
        '1. Grounding: Rely strictly on the conceptual facts in the provided source chunks. Each question must include valid citation_indexes referencing the supporting [Source] numbers.\n'
        '2. Formula & Notation Auditing (Auto-Correction): Textbooks may contain typos, font encoding artifacts, missing brackets, or malformed expressions. NEVER copy broken or garbled formulas verbatim. Intelligently detect, correct, and restore all mathematical, logical, chemical, or technical notations to their canonical, mathematically sound forms.\n'
        '3. LaTeX Formatting: Enclose all mathematical notations, equations, variables, set operations, and expressions in standard LaTeX using $...$ (or $$...$$ for block display) in question text, options, and explanations.\n'
        '4. Natural Presentation: Use clear, unambiguous, high-quality Vietnamese. Ensure questions and options are pedagogically sound and logically consistent.\n'
        '5. Strict Structure: Each question must have exactly four distinct options, one zero-based correct_index (0-3), and an evidence-backed explanation.\n\n'
        f'Source chunks:\n{context}'
    )
    try:
        client = gemini_client()
        response = client.models.generate_content(
            model=GENERATION_MODEL,
            contents=instruction,
            config=types.GenerateContentConfig(response_mime_type='application/json', response_schema=QuizDraft.model_json_schema()),
        )
        draft = QuizDraft.model_validate(json.loads(response.text))
    except PipelineError:
        raise
    except Exception as exc:
        LOGGER.exception('Gemini quiz-generation request failed')
        if is_rate_limited(exc):
            raise PipelineError('GEMINI_RATE_LIMITED', 'Gemini is temporarily rate limited. Please retry shortly.', 'generation', 429) from exc
        raise PipelineError('QUIZ_GENERATION_FAILED', 'Gemini could not generate a valid quiz.', 'generation', 502) from exc

    if len(draft.questions) != request.question_count:
        raise PipelineError('QUIZ_SCHEMA_INVALID', 'Gemini returned a different number of questions than requested.', 'generation', 502)
    output_questions: list[QuizQuestionOutput] = []
    for question in draft.questions:
        if len({option.strip().casefold() for option in question.options}) != 4 or any(not option.strip() for option in question.options):
            raise PipelineError('QUIZ_SCHEMA_INVALID', 'Every quiz question must contain four distinct non-empty options.', 'generation', 502)
        if any(index < 1 or index > len(matches) for index in question.citation_indexes):
            raise PipelineError('UNGROUNDABLE_CITATION', 'Gemini returned a quiz citation outside the retrieved source set.', 'generation', 502)
        selected_indexes = list(dict.fromkeys(question.citation_indexes))
        citations = [QuizCitation(
            vector_id=matches[index - 1]['vector_id'],
            source_locator=matches[index - 1]['source_locator'],
            document_id=matches[index - 1]['document_id'],
            document_name=matches[index - 1]['document_name'],
        ) for index in selected_indexes]
        output_questions.append(QuizQuestionOutput(
            question=question.question,
            options=question.options,
            correct_index=question.correct_index,
            explanation=question.explanation,
            citations=citations,
        ))
    output = QuizOutput(title=draft.title, description=draft.description, questions=output_questions)
    return {
        'quiz': output.model_dump(),
        'retrieval': {
            'query': retrieved['query'],
            'match_count': len(matches),
            'embedding_model': retrieved['embedding_model'],
            'collection': retrieved['collection'],
        },
        'generation_model': GENERATION_MODEL,
    }


@APP.delete('/internal/v1/documents/{document_id}/vectors', dependencies=[Depends(require_service_token)])
def delete_document_vectors(document_id: int) -> dict[str, Any]:
    try:
        chroma_collection().delete(where={'document_id': str(document_id)})
    except PipelineError:
        raise
    except Exception as exc:
        raise PipelineError('VECTOR_DELETE_FAILED', 'ChromaDB could not remove the document vectors.', 'cleanup', 503) from exc
    return {'deleted': True, 'document_id': document_id}


@APP.delete('/internal/v1/courses/{course_id}/vectors', dependencies=[Depends(require_service_token)])
def delete_course_vectors(course_id: int) -> dict[str, Any]:
    try:
        chroma_collection().delete(where={'course_id': str(course_id)})
    except PipelineError:
        raise
    except Exception as exc:
        raise PipelineError('VECTOR_DELETE_FAILED', 'ChromaDB could not remove the course vectors.', 'cleanup', 503) from exc
    return {'deleted': True, 'course_id': course_id}
