<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SprintOneApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_invalidates_token(): void
    {
        User::create(['name' => 'Lecturer', 'email' => 'l@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'lecturer']);
        $token = $this->postJson('/api/auth/login', ['email' => 'l@test.dev', 'password' => 'Password123!'])->assertOk()->json('token');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_student_cannot_create_or_view_unassigned_course(): void
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'l@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'lecturer']);
        $student = User::create(['name' => 'Student', 'email' => 's@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'student']);
        $course = Course::create(['lecturer_id' => $lecturer->id, 'name' => 'Private', 'code' => 'P1']);
        $token = 'student-token'; $student->update(['api_token_hash' => hash('sha256', $token)]);
        $this->withToken($token)->postJson('/api/courses', ['name' => 'Nope', 'code' => 'N1'])->assertForbidden();
        $this->withToken($token)->getJson('/api/courses/'.$course->id)->assertForbidden();
    }

    public function test_lecturer_can_create_course_and_grant_student_access(): void
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'l@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'lecturer']);
        $student = User::create(['name' => 'Student', 'email' => 's@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'student']);
        $lecturerToken = 'lecturer-token'; $lecturer->update(['api_token_hash' => hash('sha256', $lecturerToken)]);
        $studentToken = 'student-token'; $student->update(['api_token_hash' => hash('sha256', $studentToken)]);

        $courseId = $this->withToken($lecturerToken)->postJson('/api/courses', [
            'name' => 'Foundations', 'code' => 'FLIP-101', 'description' => 'Demo course',
        ])->assertCreated()->json('course.id');

        $this->withToken($lecturerToken)->postJson('/api/courses/'.$courseId.'/enrollments', ['student_id' => $student->id])->assertOk();
        $this->withToken($studentToken)->getJson('/api/courses')->assertOk()->assertJsonCount(1, 'courses');
        $this->withToken($studentToken)->getJson('/api/courses/'.$courseId)->assertOk()->assertJsonPath('course.code', 'FLIP-101');
    }

    public function test_only_course_owner_can_upload_allowed_document(): void
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'l@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'lecturer']);
        $other = User::create(['name' => 'Other', 'email' => 'o@test.dev', 'password' => Hash::make('Password123!'), 'role' => 'lecturer']);
        $course = Course::create(['lecturer_id' => $lecturer->id, 'name' => 'Course', 'code' => 'C1']);
        $ownerToken = 'owner'; $lecturer->update(['api_token_hash' => hash('sha256', $ownerToken)]);
        $otherToken = 'other'; $other->update(['api_token_hash' => hash('sha256', $otherToken)]);
        $this->withToken($otherToken)->post('/api/courses/'.$course->id.'/documents', ['document' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')])->assertForbidden();
        $this->withToken($ownerToken)->post('/api/courses/'.$course->id.'/documents', ['document' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')])->assertCreated()->assertJsonPath('document.processing_status', 'uploaded_pending_processing');
        $this->withToken($ownerToken)->post('/api/courses/'.$course->id.'/documents', ['document' => UploadedFile::fake()->create('note.txt', 10, 'text/plain')])->assertUnprocessable();
        $this->withToken($ownerToken)->post('/api/courses/'.$course->id.'/documents', ['document' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertUnprocessable();
        $documentId = $this->withToken($ownerToken)->getJson('/api/courses/'.$course->id.'/documents')->assertOk()->assertJsonCount(1, 'documents')->json('documents.0.id');
        $this->withToken($ownerToken)->deleteJson('/api/courses/'.$course->id.'/documents/'.$documentId)->assertOk();
    }
}
