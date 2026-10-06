<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DocumentProcessingRun;
use App\Models\LearningObject;
use App\Models\LearningObjectGenerationRun;
use App\Models\TeachingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SprintThreeEnrollmentAndTasksTest extends TestCase
{
    use RefreshDatabase;

    private function createLecturer(string $email = 'lecturer@flts.local'): array
    {
        $token = Str::random(40);
        $user = User::create([
            'name' => 'Lecturer '.Str::random(4),
            'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => 'lecturer',
            'account_status' => 'active',
            'api_token_hash' => hash('sha256', $token),
        ]);

        return [$user, $token];
    }

    private function createStudent(string $email = 'student@flts.local', string $status = 'active'): array
    {
        $token = Str::random(40);
        $user = User::create([
            'name' => 'Student '.Str::random(4),
            'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => 'student',
            'account_status' => $status,
            'api_token_hash' => hash('sha256', $token),
        ]);

        return [$user, $token];
    }

    public function test_lecturer_can_list_enrolled_students(): void
    {
        [$owner, $ownerToken] = $this->createLecturer('owner@flts.local');
        [$otherLecturer, $otherToken] = $this->createLecturer('other@flts.local');
        [$student, $studentToken] = $this->createStudent('enrolled@flts.local');

        $course = Course::create([
            'lecturer_id' => $owner->id,
            'name' => 'Software Architecture',
            'code' => 'SE-301',
        ]);

        $course->students()->attach($student->id);

        // Owner can list students
        $res = $this->withToken($ownerToken)->getJson("/api/courses/{$course->id}/students");
        $res->assertOk();
        $res->assertJsonCount(1, 'students');
        $res->assertJsonPath('students.0.id', $student->id);
        $res->assertJsonPath('students.0.email', 'enrolled@flts.local');

        // Other lecturer cannot access
        $this->withToken($otherToken)->getJson("/api/courses/{$course->id}/students")->assertForbidden();

        // Student cannot access
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/students")->assertForbidden();
    }

    public function test_lecturer_can_search_available_students(): void
    {
        [$owner, $ownerToken] = $this->createLecturer();
        [$enrolledStudent] = $this->createStudent('enrolled@flts.local');
        [$availableActive] = $this->createStudent('alice@flts.local');
        [$suspendedStudent] = $this->createStudent('bob@flts.local', 'suspended');

        $course = Course::create([
            'lecturer_id' => $owner->id,
            'name' => 'Data Science',
            'code' => 'DS-101',
        ]);
        $course->students()->attach($enrolledStudent->id);

        $res = $this->withToken($ownerToken)->getJson("/api/courses/{$course->id}/students/available");
        $res->assertOk();
        $students = $res->json('students');
        $ids = collect($students)->pluck('id')->all();

        // Should include availableActive
        $this->assertContains($availableActive->id, $ids);
        // Should NOT include already enrolled
        $this->assertNotContains($enrolledStudent->id, $ids);
        // Should NOT include suspended
        $this->assertNotContains($suspendedStudent->id, $ids);

        // Test search filter
        $searchRes = $this->withToken($ownerToken)->getJson("/api/courses/{$course->id}/students/available?q=alice");
        $searchRes->assertOk();
        $this->assertCount(1, $searchRes->json('students'));
        $this->assertSame($availableActive->id, $searchRes->json('students.0.id'));
    }

    public function test_lecturer_can_enroll_and_unenroll_student(): void
    {
        [$owner, $ownerToken] = $this->createLecturer();
        [$otherOwner, $otherToken] = $this->createLecturer('other2@flts.local');
        [$activeStudent] = $this->createStudent('active_student@flts.local');
        [$suspendedStudent] = $this->createStudent('suspended_student@flts.local', 'suspended');

        $course = Course::create([
            'lecturer_id' => $owner->id,
            'name' => 'Web Development',
            'code' => 'WEB-201',
        ]);

        // Enrolling suspended student should fail with 422
        $this->withToken($ownerToken)->postJson("/api/courses/{$course->id}/enrollments", [
            'student_id' => $suspendedStudent->id,
        ])->assertStatus(422);

        // Enrolling active student succeeds
        $enrollRes = $this->withToken($ownerToken)->postJson("/api/courses/{$course->id}/enrollments", [
            'student_id' => $activeStudent->id,
        ]);
        $enrollRes->assertOk();
        $this->assertTrue($course->students()->where('users.id', $activeStudent->id)->exists());

        // Other lecturer cannot unenroll
        $this->withToken($otherToken)->deleteJson("/api/courses/{$course->id}/enrollments/{$activeStudent->id}")
            ->assertForbidden();

        // Owner can unenroll
        $unenrollRes = $this->withToken($ownerToken)->deleteJson("/api/courses/{$course->id}/enrollments/{$activeStudent->id}");
        $unenrollRes->assertOk();
        $this->assertFalse($course->students()->where('users.id', $activeStudent->id)->exists());

        // Unenrolling non-enrolled student returns 404
        $this->withToken($ownerToken)->deleteJson("/api/courses/{$course->id}/enrollments/{$activeStudent->id}")
            ->assertNotFound();
    }

    public function test_background_tasks_aggregation(): void
    {
        [$owner, $ownerToken] = $this->createLecturer();
        [$other, $otherToken] = $this->createLecturer('another@flts.local');
        [$student, $studentToken] = $this->createStudent();

        $course1 = Course::create(['lecturer_id' => $owner->id, 'name' => 'Course 1', 'code' => 'C1']);
        $course2 = Course::create(['lecturer_id' => $other->id, 'name' => 'Course 2', 'code' => 'C2']);

        // Document for Course 1
        $doc = TeachingDocument::create([
            'course_id' => $course1->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'Slide1.pdf',
            'stored_path' => 'documents/Slide1.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'processing_status' => 'processing',
        ]);
        $docRun = DocumentProcessingRun::create([
            'teaching_document_id' => $doc->id,
            'requested_by' => $owner->id,
            'attempt_number' => 1,
            'status' => 'processing',
            'stage' => 'chunking',
            'started_at' => now(),
        ]);

        // Learning object quiz run for Course 1
        $lo = LearningObject::create([
            'course_id' => $course1->id,
            'type' => 'quiz',
            'title' => 'Quiz Chapter 1',
            'status' => 'draft',
            'created_by' => $owner->id,
        ]);
        $quizRun = LearningObjectGenerationRun::create([
            'learning_object_id' => $lo->id,
            'request_id' => (string) Str::uuid(),
            'attempt_number' => 1,
            'status' => 'queued',
            'generation_params' => ['topic' => 'Chapter 1'],
            'started_at' => now(),
        ]);

        // Document for Course 2 (should not appear in owner's tasks)
        $docOther = TeachingDocument::create([
            'course_id' => $course2->id,
            'uploaded_by' => $other->id,
            'original_name' => 'OtherSlide.pdf',
            'stored_path' => 'documents/OtherSlide.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 2048,
            'processing_status' => 'processing',
        ]);
        DocumentProcessingRun::create([
            'teaching_document_id' => $docOther->id,
            'requested_by' => $other->id,
            'attempt_number' => 1,
            'status' => 'processing',
            'stage' => 'chunking',
            'started_at' => now(),
        ]);

        // Owner fetches tasks
        $res = $this->withToken($ownerToken)->getJson('/api/background-tasks');
        $res->assertOk();
        $tasks = $res->json('tasks');
        $this->assertCount(2, $tasks);
        $this->assertSame(2, $res->json('active_count'));

        $titles = collect($tasks)->pluck('title')->all();
        $this->assertContains('Slide1.pdf', $titles);
        $this->assertContains('Quiz Chapter 1', $titles);
        $this->assertNotContains('OtherSlide.pdf', $titles);

        // Student cannot access background tasks
        $this->withToken($studentToken)->getJson('/api/background-tasks')->assertForbidden();
    }
}
