<?php

namespace Tests\Feature;

use App\Jobs\ProcessTeachingDocument;
use App\Models\Course;
use App\Models\DocumentProcessingRun;
use App\Models\TeachingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
