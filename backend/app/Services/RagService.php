<?php

namespace App\Services;

use App\Models\Course;
use App\Models\TeachingDocument;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RagService
{
    /**
     * Laravel remains the public authorization boundary. FastAPI is reachable only on the Docker network.
     */
    public function processDocument(TeachingDocument $document, int $runId): array
    {
        $stream = fopen(storage_path('app/'.$document->stored_path), 'r');
        if ($stream === false) {
            throw new \RuntimeException('The uploaded source file is no longer available in local storage.');
        }

        try {
            $response = $this->client()->attach('file', $stream, $document->original_name)->post('/internal/v1/documents/process', [
                'document_id' => (string) $document->id,
                'course_id' => (string) $document->course_id,
                'processing_run_id' => (string) $runId,
                'mime_type' => $document->mime_type,
                'extension' => $document->extension,
            ]);
        } finally {
            fclose($stream);
        }

        return $this->unwrap($response, 'document processing');
    }

    public function search(Course $course, string $query, int $topK, array $documentIds = []): array
    {
        return $this->unwrap($this->client()->asJson()->post('/internal/v1/retrieval/search', [
            'course_id' => $course->id,
            'query' => $query,
            'top_k' => $topK,
            'document_ids' => array_values($documentIds),
        ]), 'retrieval');
    }

    public function generateEvidence(Course $course, string $prompt, int $topK): array
    {
        return $this->unwrap($this->client()->asJson()->post('/internal/v1/evidence/generate', [
            'course_id' => $course->id,
            'prompt' => $prompt,
            'top_k' => $topK,
        ]), 'evidence generation');
    }

    public function generateQuiz(Course $course, array $parameters): array
    {
        return $this->unwrap($this->client(120)->asJson()->post('/internal/v1/generation/quiz', [
            'course_id' => $course->id,
            'topic' => $parameters['topic'],
            'difficulty' => $parameters['difficulty'],
            'question_count' => $parameters['question_count'],
            'document_ids' => array_values($parameters['document_ids'] ?? []),
            'top_k' => $parameters['top_k'],
        ]), 'quiz generation');
    }

    public function deleteDocumentVectors(TeachingDocument $document): void
    {
        $this->unwrap($this->client()->delete('/internal/v1/documents/'.$document->id.'/vectors'), 'vector cleanup');
    }

    public function deleteCourseVectors(Course $course): void
    {
        $this->unwrap($this->client(30)->delete('/internal/v1/courses/'.$course->id.'/vectors'), 'course vector cleanup');
    }

    private function client(int $timeout = 600)
    {
        return Http::baseUrl(rtrim((string) config('rag.url'), '/'))
            ->acceptJson()
            ->withToken((string) config('rag.token'))
            ->connectTimeout(5)
            ->timeout($timeout);
    }

    private function unwrap(Response $response, string $operation): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $error = $response->json('detail');
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        if ($response->status() === 429 || $code === 'GEMINI_RATE_LIMITED') {
            throw new RagServiceException('Gemini is temporarily rate limited. Please retry shortly.', 429);
        }
        if ($response->status() === 422 && $code === 'NO_GROUNDED_CONTEXT') {
            throw new RagServiceException('No processed source chunks matched this request.', 422);
        }

        // Do not pass FastAPI/provider diagnostics through the public Laravel API.
        throw new RagServiceException("AI service {$operation} is temporarily unavailable.", 503);
    }
}
