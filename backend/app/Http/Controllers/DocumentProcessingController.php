<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTeachingDocument;
use App\Models\Course;
use App\Models\DocumentProcessingRun;
use App\Models\TeachingDocument;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentProcessingController
{
    public function start(Request $request, Course $course, TeachingDocument $document): JsonResponse
    {
        $this->ensureOwner($request, $course, $document);
        if ($document->processing_status === 'processing') {
            return response()->json(['message' => 'This document is already being processed.'], 422);
        }

        $run = $this->queueRun($request, $document);
        return response()->json(['run' => $this->runSummary($run), 'message' => 'Document processing queued.'], 202);
    }

    public function retry(Request $request, Course $course, TeachingDocument $document): JsonResponse
    {
        $this->ensureOwner($request, $course, $document);
        abort_unless($document->processing_status === 'failed', 422, 'Only a failed document can be retried.');

        $run = $this->queueRun($request, $document);
        return response()->json(['run' => $this->runSummary($run), 'message' => 'Document retry queued.'], 202);
    }

    public function show(Request $request, Course $course, TeachingDocument $document): JsonResponse
    {
        $this->ensureOwner($request, $course, $document);
        $document->load(['latestProcessingRun.extraction', 'latestProcessingRun.chunks.vectorReference']);
        return response()->json([
            'document' => $document,
            'run' => $document->latestProcessingRun ? $this->runSummary($document->latestProcessingRun, true) : null,
        ]);
    }

    public function search(Request $request, Course $course, RagService $rag): JsonResponse
    {
        $this->ensureCourseOwner($request, $course);
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:2000'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:10'],
            'document_ids' => ['nullable', 'array', 'max:50'],
            'document_ids.*' => ['integer'],
        ]);
        $documentIds = $data['document_ids'] ?? [];
        if ($documentIds !== [] && $course->documents()->whereIn('id', $documentIds)->count() !== count(array_unique($documentIds))) {
            return response()->json(['message' => 'One or more documents do not belong to this course.'], 422);
        }

        try {
            $results = $rag->search($course, $data['query'], $data['top_k'] ?? 5, $documentIds);
        } catch (\RuntimeException $exception) {
            // FastAPI messages can contain deployment diagnostics; keep the browser response safe and stable.
            return response()->json(['message' => 'RAG retrieval is temporarily unavailable.'], 503);
        }
        return response()->json($results);
    }

    public function generateEvidence(Request $request, Course $course, RagService $rag): JsonResponse
    {
        $this->ensureCourseOwner($request, $course);
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:2000'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        try {
            return response()->json($rag->generateEvidence($course, $data['prompt'], $data['top_k'] ?? 5));
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Grounded evidence generation is temporarily unavailable.'], 503);
        }
    }

    private function queueRun(Request $request, TeachingDocument $document): DocumentProcessingRun
    {
        $run = DB::transaction(function () use ($request, $document) {
            $document->refresh();
            $attempt = ((int) $document->processingRuns()->max('attempt_number')) + 1;
            $run = $document->processingRuns()->create([
                'requested_by' => $request->user()->id,
                'attempt_number' => $attempt,
                'status' => 'queued',
                'stage' => 'queued',
                // Versioned input makes later parser/chunk changes auditable rather than silently altering a run.
                'pipeline_config' => [
                    'parser' => 'pypdf/python-docx',
                    'chunking' => 'paragraph-window-v1',
                    // Record the selected model with each run so environment changes do not rewrite history.
                    'embedding_model' => (string) config('rag.embedding_model'),
                ],
            ]);
            $document->update([
                'latest_processing_run_id' => $run->id,
                'processing_status' => 'processing',
                'processing_error' => null,
            ]);
            return $run;
        });

        ProcessTeachingDocument::dispatch($run->id);
        return $run;
    }

    private function runSummary(DocumentProcessingRun $run, bool $includeCounts = false): array
    {
        $summary = [
            'id' => $run->id, 'attempt_number' => $run->attempt_number, 'status' => $run->status,
            'stage' => $run->stage, 'error_detail' => $run->error_detail,
            'started_at' => $run->started_at, 'finished_at' => $run->finished_at,
            'created_at' => $run->created_at,
        ];
        if ($includeCounts) {
            $summary['extraction'] = $run->extraction ? [
                'character_count' => $run->extraction->character_count,
                'page_count' => $run->extraction->page_count,
            ] : null;
            $summary['chunk_count'] = $run->chunks->count();
        }
        return $summary;
    }

    private function ensureOwner(Request $request, Course $course, TeachingDocument $document): void
    {
        abort_unless($document->course_id === $course->id, 404);
        $this->ensureCourseOwner($request, $course);
    }

    private function ensureCourseOwner(Request $request, Course $course): void
    {
        // Lecturer role middleware does not establish ownership of this particular course.
        abort_unless($course->lecturer_id === $request->user()->id, 403);
    }
}
