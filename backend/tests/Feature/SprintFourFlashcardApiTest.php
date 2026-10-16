<?php

namespace Tests\Feature;

use App\Jobs\GenerateFlashcardLearningObject;
use App\Models\Course;
use App\Models\LearningObject;
use App\Models\LearningObjectGenerationRun;
use App\Models\User;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SprintFourFlashcardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_queues_flashcards_and_duplicate_request_is_idempotent(): void
    {
        [$lecturer, , $course, $lecturerToken] = $this->courseActors();
        Queue::fake();
        $requestId = (string) Str::uuid();
        $payload = $this->generationPayload($requestId);

        $response = $this->withToken($lecturerToken)
            ->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $payload)
            ->assertAccepted()
            ->assertJsonPath('learning_object.type', 'flashcard')
            ->assertJsonPath('learning_object.status', 'draft')
            ->assertJsonPath('learning_object.generation.status', 'queued')
            ->assertJsonPath('learning_object.flashcard', null);

        $objectId = $response->json('learning_object.id');
        $this->assertDatabaseHas('learning_objects', [
            'id' => $objectId, 'course_id' => $course->id, 'created_by' => $lecturer->id,
            'type' => 'flashcard', 'status' => 'draft',
        ]);
        Queue::assertPushed(GenerateFlashcardLearningObject::class, fn ($job) => $job->runId === $response->json('learning_object.generation.id'));

        $this->withToken($lecturerToken)
            ->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $payload)
            ->assertAccepted()->assertJsonPath('learning_object.id', $objectId);
        $this->assertDatabaseCount('learning_objects', 1);
        $this->assertDatabaseCount('learning_object_generation_runs', 1);
    }

    public function test_generation_job_persists_grounded_cards_and_parameters(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        Queue::fake();
        Http::fake(['*' => Http::response($this->generatedFlashcards(), 200)]);
        $response = $this->withToken($lecturerToken)
            ->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $this->generationPayload((string) Str::uuid()))
            ->assertAccepted();
        $run = LearningObjectGenerationRun::findOrFail($response->json('learning_object.generation.id'));

        (new GenerateFlashcardLearningObject($run->id))->handle(app(RagService::class));

        $this->assertDatabaseHas('learning_object_generation_runs', ['id' => $run->id, 'status' => 'completed']);
        $version = LearningObject::findOrFail($response->json('learning_object.id'))->versions()->firstOrFail();
        $this->assertCount(5, $version->content_payload['cards']);
        $this->assertSame('5:14:0', $version->content_payload['cards'][0]['citations'][0]['vector_id']);
        $this->assertSame(5, $version->generation_params['max_pages']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/internal/v1/learning-objects/generate-flashcards')
            && $request['course_id'] === $course->id
            && $request['card_count'] === 5
            && $request['max_pages'] === 5);
    }

    public function test_non_owner_and_student_cannot_generate_flashcards(): void
    {
        [, , $course, , $studentToken] = $this->courseActors();
        $other = User::create(['name' => 'Other lecturer', 'email' => 'other4@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $otherToken = $this->tokenFor($other, 'other-token');
        $payload = $this->generationPayload((string) Str::uuid());

        $this->withToken($otherToken)->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $payload)->assertForbidden();
        $payload['request_id'] = (string) Str::uuid();
        $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $payload)->assertForbidden();
        $this->assertDatabaseCount('learning_objects', 0);
    }

    public function test_lecturer_edits_versions_and_publishes_flashcards_for_enrolled_student(): void
    {
        [, , $course, $lecturerToken, $studentToken] = $this->courseActors();
        $object = $this->createDraft($course, $lecturerToken);
        $cards = $object->latestVersion->content_payload['cards'];
        $cards[0]['front'] = 'Khái niệm đã được giảng viên chỉnh sửa?';

        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$object->id}")->assertNotFound();
        $this->withToken($lecturerToken)->patchJson("/api/courses/{$course->id}/learning-objects/{$object->id}", [
            'title' => 'Bộ thẻ đã hiệu đính',
            'description' => 'Nội dung được giảng viên kiểm tra.',
            'cards' => $cards,
        ])->assertOk()
            ->assertJsonPath('learning_object.current_version', 2)
            ->assertJsonPath('learning_object.flashcard.cards.0.front', 'Khái niệm đã được giảng viên chỉnh sửa?');

        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/publish")
            ->assertOk()->assertJsonPath('learning_object.status', 'published');
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$object->id}")
            ->assertOk()
            ->assertJsonPath('learning_object.type', 'flashcard')
            ->assertJsonCount(5, 'learning_object.flashcard.cards');
        $this->assertDatabaseHas('learning_object_versions', ['learning_object_id' => $object->id, 'version_number' => 2]);
    }

    public function test_failed_flashcard_generation_is_safe_and_retryable(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        Queue::fake();
        Http::fake(['*' => Http::response(['detail' => [
            'code' => 'FLASHCARD_GENERATION_FAILED', 'message' => 'private provider detail', 'stage' => 'generation',
        ]], 502)]);
        $response = $this->withToken($lecturerToken)
            ->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $this->generationPayload((string) Str::uuid()))
            ->assertAccepted();
        $run = LearningObjectGenerationRun::findOrFail($response->json('learning_object.generation.id'));
        (new GenerateFlashcardLearningObject($run->id))->handle(app(RagService::class));

        $this->withToken($lecturerToken)->getJson("/api/courses/{$course->id}/learning-objects/{$response->json('learning_object.id')}")
            ->assertOk()->assertJsonPath('learning_object.generation.status', 'failed')
            ->assertJsonMissing(['private provider detail']);
        $retry = $this->withToken($lecturerToken)
            ->postJson("/api/courses/{$course->id}/learning-objects/{$response->json('learning_object.id')}/generation-runs/retry")
            ->assertAccepted()->assertJsonPath('learning_object.generation.attempt_number', 2);
        Queue::assertPushed(GenerateFlashcardLearningObject::class, fn ($job) => $job->runId === $retry->json('learning_object.generation.id'));
    }

    public function test_background_tasks_provides_direct_flashcard_editor_url_when_completed(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        $object = $this->createDraft($course, $lecturerToken);

        $res = $this->withToken($lecturerToken)->getJson('/api/background-tasks');
        $res->assertOk();
        $tasks = $res->json('tasks');
        $this->assertNotEmpty($tasks);
        $flashcardTask = collect($tasks)->firstWhere('type', 'flashcard_generation');
        $this->assertNotNull($flashcardTask);
        $this->assertSame('completed', $flashcardTask['status']);
        $this->assertSame("/lecturer/courses/{$course->id}/flashcards/{$object->id}", $flashcardTask['target_url']);
    }

    private function courseActors(): array
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'lecturer4@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $student = User::create(['name' => 'Student', 'email' => 'student4@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'student']);
        $course = Course::create(['name' => 'Sprint 4 Course', 'code' => 'SPR4', 'description' => 'Flashcard course', 'lecturer_id' => $lecturer->id]);
        $course->students()->attach($student->id);
        return [$lecturer, $student, $course, $this->tokenFor($lecturer, 'lecturer-token'), $this->tokenFor($student, 'student-token')];
    }

    private function tokenFor(User $user, string $token): string
    {
        $user->update(['api_token_hash' => hash('sha256', $token)]);
        return $token;
    }

    private function generationPayload(string $requestId): array
    {
        return [
            'topic' => 'Không gian vector', 'difficulty' => 'medium', 'card_count' => 5,
            'top_k' => 8, 'max_pages' => 5, 'request_id' => $requestId,
        ];
    }

    private function createDraft(Course $course, string $token): LearningObject
    {
        Queue::fake();
        Http::fake(['*' => Http::response($this->generatedFlashcards(), 200)]);
        $response = $this->withToken($token)
            ->postJson("/api/courses/{$course->id}/learning-objects/flashcards", $this->generationPayload((string) Str::uuid()))
            ->assertAccepted();
        (new GenerateFlashcardLearningObject($response->json('learning_object.generation.id')))->handle(app(RagService::class));
        return LearningObject::with('latestVersion')->findOrFail($response->json('learning_object.id'));
    }

    private function generatedFlashcards(): array
    {
        return [
            'flashcards' => [
                'title' => 'Không gian vector',
                'description' => 'Các khái niệm trọng tâm.',
                'cards' => collect(range(1, 5))->map(fn ($index) => [
                    'id' => $index,
                    'front' => "Khái niệm {$index}?",
                    'back' => "Giải thích có căn cứ {$index}.",
                    'hint' => 'Nhớ lại định nghĩa.',
                    'citations' => [[
                        'vector_id' => '5:14:0', 'source_locator' => 'page 5',
                        'document_id' => 5, 'document_name' => 'chapter.pdf',
                    ]],
                ])->all(),
            ],
            'retrieval' => ['match_count' => 1, 'max_pages' => 5],
            'generation_model' => 'gemini-2.5-flash',
        ];
    }
}
