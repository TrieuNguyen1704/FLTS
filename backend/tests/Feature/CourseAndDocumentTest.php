<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\TeachingDocument;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseAndDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function account(string $name, string $role = 'lecturer'): User
    {
        return User::create([
            'name' => $name, 'email' => $name.'@test.dev', 'password' => 'unused-in-token-tests',
            'role' => $role, 'api_token_hash' => hash('sha256', $name),
        ]);
    }

    private function ownedCourse(User $owner, string $code = 'CMU-SE 450'): Course
    {
        return $owner->courses()->create(['name' => 'Software Engineering', 'code' => $code]);
    }

    public function test_lecturer_creates_course_lists_only_owned_courses_and_reads_details(): void
    {
        $owner = $this->account('owner');
        $other = $this->account('other');
        $otherCourse = $this->ownedCourse($other, 'PRIVATE-101');
        $id = $this->withToken('owner')->postJson('/api/courses', [
            'name' => ' Software Engineering ', 'code' => ' CMU-SE 450 ',
            'description' => 'Teaching materials', 'lecturer_id' => $other->id,
        ])->assertCreated()->assertJsonPath('course.lecturer_id', $owner->id)->json('course.id');
        $this->getJson('/api/courses')->assertOk()->assertJsonCount(1, 'courses');
        $this->getJson('/api/courses/'.$id)->assertOk()->assertJsonPath('course.code', 'CMU-SE 450');
        $this->getJson('/api/courses/'.$otherCourse->id)->assertForbidden();
    }

    public function test_course_input_validation_and_unique_code_on_create_and_update(): void
    {
        $owner = $this->account('owner');
        $course = $this->ownedCourse($owner);
        $other = $this->ownedCourse($owner, 'OTHER-101');
        $this->withToken('owner')->postJson('/api/courses', ['name' => 'Duplicate', 'code' => ' CMU-SE 450 '])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/courses', ['name' => ' ', 'code' => ' '])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);
        $this->patchJson('/api/courses/'.$course->id, ['code' => $course->code])->assertOk();
        $this->patchJson('/api/courses/'.$other->id, ['code' => $course->code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_database_itself_rejects_duplicate_course_codes(): void
    {
        $owner = $this->account('owner');
        $this->ownedCourse($owner);
        $this->expectException(QueryException::class);
        $this->ownedCourse($owner);
    }

    public function test_enrollment_is_unique_and_student_relation_works(): void
    {
        $owner = $this->account('owner');
        $student = $this->account('student', 'student');
        $course = $this->ownedCourse($owner);
        $this->withToken('owner')->postJson('/api/courses/'.$course->id.'/enrollments', ['student_id' => $student->id])->assertOk();
        $this->postJson('/api/courses/'.$course->id.'/enrollments', ['student_id' => $student->id])->assertOk();
        $this->assertDatabaseCount('course_enrollments', 1);
        $this->assertTrue($course->students->contains($student));
        $this->withToken('student')->getJson('/api/courses/'.$course->id)->assertOk();
    }

    public function test_pdf_upload_persists_file_metadata_and_pending_state(): void
    {
        $owner = $this->account('owner');
        $course = $this->ownedCourse($owner);
        $file = UploadedFile::fake()->create('lecture.pdf', 10, 'application/pdf');
        $response = $this->withToken('owner')->post('/api/courses/'.$course->id.'/documents', ['document' => $file])
            ->assertCreated()->assertJsonPath('document.processing_status', 'uploaded_pending_processing')
            ->assertJsonPath('document.original_name', 'lecture.pdf')->assertJsonPath('document.size_bytes', 10240);
        $path = $response->json('document.stored_path');
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('documents/'.$course->id.'/', $path);
        $this->assertNotSame('documents/'.$course->id.'/lecture.pdf', $path);
        $this->assertDatabaseHas('teaching_documents', ['course_id' => $course->id, 'uploaded_by' => $owner->id]);
        $this->assertSame($course->id, TeachingDocument::first()->course->id);
    }

    public function test_doc_and_docx_uploads_are_supported(): void
    {
        $owner = $this->account('owner');
        $course = $this->ownedCourse($owner);
        foreach (['doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] as $extension => $mime) {
            $this->withToken('owner')->post('/api/courses/'.$course->id.'/documents', [
                'document' => UploadedFile::fake()->create('lecture.'.$extension, 10, $mime),
            ])->assertCreated();
        }
        $this->assertDatabaseCount('teaching_documents', 2);
    }

    public function test_missing_executable_and_spoofed_uploads_are_rejected_without_storing_files(): void
    {
        $owner = $this->account('owner');
        $course = $this->ownedCourse($owner);
        $url = '/api/courses/'.$course->id.'/documents';
        $this->withToken('owner')->post($url)->assertUnprocessable();
        foreach ([
            UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            UploadedFile::fake()->create('lecture.pdf', 10, 'text/plain'),
            UploadedFile::fake()->create('virus.exe', 10, 'application/pdf'),
        ] as $file) {
            $this->post($url, ['document' => $file])->assertUnprocessable();
        }
        $this->assertDatabaseCount('teaching_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_ten_mb_boundary_is_accepted_and_one_kb_over_is_rejected(): void
    {
        $owner = $this->account('owner');
        $course = $this->ownedCourse($owner);
        config(['documents.max_kb' => 10240]);
        $url = '/api/courses/'.$course->id.'/documents';
        $this->withToken('owner')->post($url, ['document' => UploadedFile::fake()->create('limit.pdf', 10240, 'application/pdf')])->assertCreated();
        $this->post($url, ['document' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertUnprocessable();
        $this->assertDatabaseCount('teaching_documents', 1);
    }

    public function test_non_owner_student_and_unauthenticated_uploads_are_blocked(): void
    {
        $owner = $this->account('owner');
        $this->account('other');
        $this->account('student', 'student');
        $course = $this->ownedCourse($owner);
        $url = '/api/courses/'.$course->id.'/documents';
        foreach (['other', 'student'] as $token) {
            $this->withToken($token)->post($url, ['document' => UploadedFile::fake()->create('lecture.pdf', 10, 'application/pdf')])->assertForbidden();
        }
        $this->withHeader('Authorization', '')->post($url)->assertUnauthorized();
        $this->assertDatabaseCount('teaching_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_same_original_filename_does_not_overwrite_previous_upload(): void
    {
        $owner = $this->account('owner');
        $course = $this->ownedCourse($owner);
        $paths = [];
        for ($i = 0; $i < 2; $i++) {
            $paths[] = $this->withToken('owner')->post('/api/courses/'.$course->id.'/documents', [
                'document' => UploadedFile::fake()->create('lecture.pdf', 10, 'application/pdf'),
            ])->assertCreated()->json('document.stored_path');
        }
        $this->assertNotSame($paths[0], $paths[1]);
        foreach ($paths as $path) Storage::disk('local')->assertExists($path);
    }
}
