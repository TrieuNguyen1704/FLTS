<?php

namespace Tests\Feature;

use App\Jobs\ProcessTeachingDocument;
use App\Models\Course;
use App\Models\DocumentProcessingRun;
use App\Models\TeachingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SprintTwoRagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_owner_can_queue_a_document_processing_run(): void
    {
        Queue::fake();
        [$lecturer, $course, $document, $token] = $this->ownedDocument();

        $this->withToken($token)->postJson("/api/courses/{$course->id}/documents/{$document->id}/processing-runs")
            ->assertStatus(202)
            ->assertJsonPath('run.status', 'queued')
            ->assertJsonPath('run.stage', 'queued');

        $this->assertDatabaseHas('document_processing_runs', ['teaching_document_id' => $document->id, 'requested_by' => $lecturer->id, 'attempt_number' => 1, 'status' => 'queued']);
        $this->assertDatabaseHas('teaching_documents', ['id' => $document->id, 'processing_status' => 'processing']);
        Queue::assertPushed(ProcessTeachingDocument::class);
    }

    public function test_non_owner_cannot_queue_or_read_processing_details(): void
    {
        Queue::fake();
        [, $course, $document] = $this->ownedDocument();
        $other = User::create(['name' => 'Other', 'email' => 'other@test.dev', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $token = 'other-token';
        $other->update(['api_token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->postJson("/api/courses/{$course->id}/documents/{$document->id}/processing-runs")->assertForbidden();
        $this->withToken($token)->getJson("/api/courses/{$course->id}/documents/{$document->id}/processing")->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_only_failed_document_can_be_retried(): void
    {
        Queue::fake();
        [, $course, $document, $token] = $this->ownedDocument();
        $this->withToken($token)->postJson("/api/courses/{$course->id}/documents/{$document->id}/processing-runs/retry")->assertUnprocessable();

        $document->update(['processing_status' => 'failed']);
        DocumentProcessingRun::create(['teaching_document_id' => $document->id, 'requested_by' => $document->uploaded_by, 'attempt_number' => 1, 'status' => 'failed', 'stage' => 'failed']);
        $this->withToken($token)->postJson("/api/courses/{$course->id}/documents/{$document->id}/processing-runs/retry")
            ->assertStatus(202)->assertJsonPath('run.attempt_number', 2);
    }

    public function test_processing_job_persists_a_successful_fastapi_result_with_vector_model_metadata(): void
    {
        [$lecturer, , $document] = $this->ownedDocument();
        $run = DocumentProcessingRun::create([
            'teaching_document_id' => $document->id, 'requested_by' => $lecturer->id,
            'attempt_number' => 1, 'status' => 'queued', 'stage' => 'queued',
        ]);
        $document->update(['latest_processing_run_id' => $run->id, 'processing_status' => 'processing']);
        $path = storage_path('app/'.$document->stored_path);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'source document content');

        Http::fake([
            '*' => Http::response([
                'embedding_model' => 'text-embedding-004',
                'extraction' => ['normalized_text' => 'Extracted chapter text', 'character_count' => 22, 'page_count' => 1, 'metadata' => ['parser' => 'pypdf']],
                'chunks' => [[
                    'chunk_index' => 0, 'content' => 'Extracted chapter text', 'source_locator' => 'page 1',
                    'token_estimate' => 4, 'content_hash' => str_repeat('a', 64), 'vector_id' => '1:1:0', 'dimensions' => 768,
                ]],
                'vector_store' => ['provider' => 'chromadb', 'collection' => 'flts_document_chunks'],
            ], 200),
        ]);

        try {
            (new ProcessTeachingDocument($run->id))->handle(app(\App\Services\RagService::class));
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseHas('document_processing_runs', ['id' => $run->id, 'status' => 'processed', 'stage' => 'completed']);
        $this->assertDatabaseHas('document_extractions', ['document_processing_run_id' => $run->id, 'character_count' => 22]);
        $this->assertDatabaseHas('document_chunks', ['document_processing_run_id' => $run->id, 'source_locator' => 'page 1']);
        $this->assertDatabaseHas('document_vector_references', ['vector_id' => '1:1:0', 'embedding_model' => 'text-embedding-004', 'dimensions' => 768]);
        Http::assertSent(function ($request) {
            $contentType = $request->header('Content-Type');
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/internal/v1/documents/process')
                && is_array($contentType)
                && isset($contentType[0])
                && str_starts_with($contentType[0], 'multipart/form-data;');
        });
    }

    public function test_terminal_job_failure_marks_the_latest_document_run_as_retryable(): void
    {
        [$lecturer, , $document] = $this->ownedDocument();
        $run = DocumentProcessingRun::create([
            'teaching_document_id' => $document->id, 'requested_by' => $lecturer->id,
            'attempt_number' => 1, 'status' => 'processing', 'stage' => 'embedding',
        ]);
        $document->update(['latest_processing_run_id' => $run->id, 'processing_status' => 'processing']);

        (new ProcessTeachingDocument($run->id))->failed(new \RuntimeException('Gemini is unavailable.'));

        $this->assertDatabaseHas('document_processing_runs', ['id' => $run->id, 'status' => 'failed', 'stage' => 'failed']);
        $this->assertDatabaseHas('teaching_documents', ['id' => $document->id, 'processing_status' => 'failed']);
        $this->assertSame('Processing failed. You can retry this document.', $document->fresh()->processing_error);
    }

    public function test_processing_document_cannot_be_deleted_while_its_job_may_still_write_vectors(): void
    {
        [, $course, $document, $token] = $this->ownedDocument();
        $document->update(['processing_status' => 'processing']);

        $this->withToken($token)->deleteJson("/api/courses/{$course->id}/documents/{$document->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This document is currently being processed and cannot be deleted yet.');

        $this->assertDatabaseHas('teaching_documents', ['id' => $document->id]);
    }

    public function test_terminal_document_deletion_requires_vector_cleanup_first(): void
    {
        [, $course, $document, $token] = $this->ownedDocument();
        $document->update(['processing_status' => 'failed']);
        Http::fake(['*' => Http::response(['deleted' => true], 200)]);

        $this->withToken($token)->deleteJson("/api/courses/{$course->id}/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Document deleted.');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), "/internal/v1/documents/{$document->id}/vectors"));
        $this->assertDatabaseMissing('teaching_documents', ['id' => $document->id]);
    }

    public function test_public_rag_errors_do_not_return_fastapi_diagnostics(): void
    {
        [, $course, , $token] = $this->ownedDocument();
        Http::fake(['*' => Http::response(['detail' => ['message' => 'internal host and provider detail']], 503)]);

        $this->withToken($token)->postJson("/api/courses/{$course->id}/retrieval-tests", ['query' => 'Find the source'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'RAG retrieval is temporarily unavailable.')
            ->assertJsonMissingPath('detail');
    }

    private function ownedDocument(): array
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'owner@test.dev', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $course = Course::create(['lecturer_id' => $lecturer->id, 'name' => 'Course', 'code' => 'RAG-1']);
        $document = TeachingDocument::create([
            'course_id' => $course->id, 'uploaded_by' => $lecturer->id, 'original_name' => 'chapter.pdf',
            'stored_path' => 'documents/1/chapter.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf',
            'size_bytes' => 100, 'processing_status' => 'uploaded_pending_processing',
        ]);
        $token = 'owner-token';
        $lecturer->update(['api_token_hash' => hash('sha256', $token)]);
        return [$lecturer, $course, $document, $token];
    }
}
