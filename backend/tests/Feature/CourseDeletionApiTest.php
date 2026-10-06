<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearningObject;
use App\Models\TeachingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_deletes_course_files_vectors_and_cascaded_records_with_exact_code(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['deleted' => true], 200)]);
        [$owner, , $student, $course, $ownerToken] = $this->actors();
        $course->students()->attach($student->id);
        $path = "documents/{$course->id}/source.pdf";
        Storage::disk('local')->put($path, 'source');
        TeachingDocument::create([
            'course_id' => $course->id, 'uploaded_by' => $owner->id, 'original_name' => 'source.pdf',
            'stored_path' => $path, 'mime_type' => 'application/pdf', 'extension' => 'pdf',
            'size_bytes' => 6, 'processing_status' => 'processed',
        ]);
        $object = LearningObject::create([
            'course_id' => $course->id, 'type' => 'quiz', 'title' => 'Quiz', 'status' => 'draft', 'created_by' => $owner->id,
        ]);
        $object->generationRuns()->create([
            'request_id' => (string) Str::uuid(), 'attempt_number' => 1, 'status' => 'completed', 'generation_params' => [],
        ]);

        $this->withToken($ownerToken)->deleteJson("/api/courses/{$course->id}", ['confirmation' => $course->code])
            ->assertOk()->assertJsonPath('message', 'Đã xóa khóa học và dữ liệu liên quan.');

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('teaching_documents', ['course_id' => $course->id]);
        $this->assertDatabaseMissing('learning_objects', ['course_id' => $course->id]);
        Storage::disk('local')->assertMissing($path);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), "/internal/v1/courses/{$course->id}/vectors"));
    }

    public function test_delete_requires_owner_role_and_exact_course_code(): void
    {
        [, $other, $student, $course, $ownerToken, $otherToken, $studentToken] = $this->actors();

        $this->withToken($otherToken)->deleteJson("/api/courses/{$course->id}", ['confirmation' => $course->code])->assertForbidden();
        $this->withToken($studentToken)->deleteJson("/api/courses/{$course->id}", ['confirmation' => $course->code])->assertForbidden();
        $this->withToken($ownerToken)->deleteJson("/api/courses/{$course->id}", ['confirmation' => 'WRONG'])->assertUnprocessable();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_delete_is_blocked_while_document_processing_is_active(): void
    {
        Storage::fake('local');
        [$owner, , , $course, $ownerToken] = $this->actors();
        TeachingDocument::create([
            'course_id' => $course->id, 'uploaded_by' => $owner->id, 'original_name' => 'source.pdf',
            'stored_path' => "documents/{$course->id}/source.pdf", 'mime_type' => 'application/pdf', 'extension' => 'pdf',
            'size_bytes' => 6, 'processing_status' => 'processing',
        ]);

        $this->withToken($ownerToken)->deleteJson("/api/courses/{$course->id}", ['confirmation' => $course->code])
            ->assertConflict();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_vector_cleanup_failure_preserves_course_and_file(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['detail' => ['code' => 'VECTOR_DELETE_FAILED']], 503)]);
        [$owner, , , $course, $ownerToken] = $this->actors();
        $path = "documents/{$course->id}/source.pdf";
        Storage::disk('local')->put($path, 'source');
        TeachingDocument::create([
            'course_id' => $course->id, 'uploaded_by' => $owner->id, 'original_name' => 'source.pdf',
            'stored_path' => $path, 'mime_type' => 'application/pdf', 'extension' => 'pdf',
            'size_bytes' => 6, 'processing_status' => 'processed',
        ]);

        $this->withToken($ownerToken)->deleteJson("/api/courses/{$course->id}", ['confirmation' => $course->code])
            ->assertStatus(503)->assertJsonMissing(['VECTOR_DELETE_FAILED']);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
        Storage::disk('local')->assertExists($path);
    }

    private function actors(): array
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@delete.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $other = User::create(['name' => 'Other', 'email' => 'other@delete.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $student = User::create(['name' => 'Student', 'email' => 'student@delete.test', 'password' => bcrypt('Password123!'), 'role' => 'student']);
        $course = Course::create(['name' => 'Delete course', 'code' => 'DEL-101', 'lecturer_id' => $owner->id]);
        return [
            $owner, $other, $student, $course,
            $this->tokenFor($owner, 'owner-token'),
            $this->tokenFor($other, 'other-token'),
            $this->tokenFor($student, 'student-token'),
        ];
    }

    private function tokenFor(User $user, string $token): string
    {
        $user->update(['api_token_hash' => hash('sha256', $token)]);
        return $token;
    }
}
