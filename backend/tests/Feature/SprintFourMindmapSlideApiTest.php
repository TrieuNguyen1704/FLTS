<?php

namespace Tests\Feature;

use App\Jobs\GenerateMindmapLearningObject;
use App\Jobs\GenerateSlideLearningObject;
use App\Models\Course;
use App\Models\LearningObject;
use App\Services\RagService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SprintFourMindmapSlideApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_queues_mindmap_idempotently_and_slide_with_valid_parameters(): void
    {
        [$lecturer, , $course, $lecturerToken] = $this->courseActors();
        Queue::fake();
        $requestId = (string) Str::uuid();
        $mindmapPayload = $this->mindmapPayload($requestId);
        $mindmap = $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/mindmaps", $mindmapPayload)
            ->assertAccepted()->assertJsonPath('learning_object.type', 'mindmap')->assertJsonPath('learning_object.generation.status', 'queued');
        Queue::assertPushed(GenerateMindmapLearningObject::class, fn ($job) => $job->runId === $mindmap->json('learning_object.generation.id'));
        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/mindmaps", $mindmapPayload)
            ->assertAccepted()->assertJsonPath('learning_object.id', $mindmap->json('learning_object.id'));

        $slide = $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/slides", $this->slidePayload((string) Str::uuid()))
            ->assertAccepted()->assertJsonPath('learning_object.type', 'slide');
        Queue::assertPushed(GenerateSlideLearningObject::class, fn ($job) => $job->runId === $slide->json('learning_object.generation.id'));
        $this->assertDatabaseCount('learning_objects', 2);
        $this->assertDatabaseHas('learning_objects', ['created_by' => $lecturer->id, 'type' => 'mindmap']);
    }

    public function test_generation_jobs_persist_grounded_payloads_and_parameters(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        Queue::fake();
        Http::fake([
            '*/generate-mindmap' => Http::response($this->generatedMindmap()),
            '*/generate-slides' => Http::response($this->generatedSlides()),
        ]);
        $mindmap = $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/mindmaps", $this->mindmapPayload((string) Str::uuid()))->assertAccepted();
        (new GenerateMindmapLearningObject($mindmap->json('learning_object.generation.id')))->handle(app(RagService::class));
        $slide = $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/slides", $this->slidePayload((string) Str::uuid()))->assertAccepted();
        (new GenerateSlideLearningObject($slide->json('learning_object.generation.id')))->handle(app(RagService::class));

        $mindmapVersion = LearningObject::findOrFail($mindmap->json('learning_object.id'))->versions()->firstOrFail();
        $slideVersion = LearningObject::findOrFail($slide->json('learning_object.id'))->versions()->firstOrFail();
        $this->assertSame('root', $mindmapVersion->content_payload['root']['id']);
        $this->assertSame('5:14:0', $mindmapVersion->content_payload['root']['citations'][0]['vector_id']);
        $this->assertCount(5, $slideVersion->content_payload['slides']);
        $this->assertSame(3, $mindmapVersion->generation_params['depth']);
        $this->assertSame(5, $slideVersion->generation_params['max_pages']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/generate-mindmap') && $request['depth'] === 3);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/generate-slides') && $request['slide_count'] === 5);
    }

    public function test_owner_edits_versions_and_students_only_see_published_content(): void
    {
        [, , $course, $lecturerToken, $studentToken] = $this->courseActors();
        [$mindmap, $slides] = $this->createDrafts($course, $lecturerToken);
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$mindmap->id}")->assertNotFound();

        $root = $mindmap->latestVersion->content_payload['root'];
        $root['label'] = 'Không gian vector đã hiệu đính';
        $this->withToken($lecturerToken)->patchJson("/api/courses/{$course->id}/learning-objects/{$mindmap->id}", [
            'title' => 'Mindmap đã hiệu đính', 'description' => 'Nội dung kiểm tra.', 'root' => $root,
        ])->assertOk()->assertJsonPath('learning_object.current_version', 2)
            ->assertJsonPath('learning_object.mindmap.root.label', 'Không gian vector đã hiệu đính');

        $slideItems = $slides->latestVersion->content_payload['slides'];
        $slideItems[0]['speaker_notes'] = 'Ghi chú đã hiệu đính.';
        $this->withToken($lecturerToken)->patchJson("/api/courses/{$course->id}/learning-objects/{$slides->id}", [
            'title' => 'Slides đã hiệu đính', 'description' => null, 'slides' => $slideItems,
        ])->assertOk()->assertJsonPath('learning_object.current_version', 2);

        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$mindmap->id}/publish")->assertOk();
        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$slides->id}/publish")->assertOk();
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$mindmap->id}")
            ->assertOk()->assertJsonPath('learning_object.mindmap.root.label', 'Không gian vector đã hiệu đính');
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$slides->id}")
            ->assertOk()->assertJsonMissingPath('learning_object.slides.slides.0.speaker_notes');
    }

    public function test_non_owner_and_student_cannot_generate_or_edit_new_types(): void
    {
        [, , $course, , $studentToken] = $this->courseActors();
        $other = User::create(['name' => 'Other', 'email' => 'other-mm@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $otherToken = $this->tokenFor($other, 'other-token');
        $this->withToken($otherToken)->postJson("/api/courses/{$course->id}/learning-objects/mindmaps", $this->mindmapPayload((string) Str::uuid()))->assertForbidden();
        $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/slides", $this->slidePayload((string) Str::uuid()))->assertForbidden();
        $this->assertDatabaseCount('learning_objects', 0);
    }

    public function test_background_tasks_return_direct_editor_urls_for_both_types(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        [$mindmap, $slides] = $this->createDrafts($course, $lecturerToken);
        $tasks = collect($this->withToken($lecturerToken)->getJson('/api/background-tasks')->assertOk()->json('tasks'));
        $this->assertSame("/lecturer/courses/{$course->id}/mindmaps/{$mindmap->id}", $tasks->firstWhere('type', 'mindmap_generation')['target_url']);
        $this->assertSame("/lecturer/courses/{$course->id}/slides/{$slides->id}", $tasks->firstWhere('type', 'slide_generation')['target_url']);
    }

    private function courseActors(): array
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'lecturer-mm@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $student = User::create(['name' => 'Student', 'email' => 'student-mm@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'student']);
        $course = Course::create(['name' => 'Sprint 4 Course', 'code' => 'SPR4-MS', 'description' => 'Mindmap and slides', 'lecturer_id' => $lecturer->id]);
        $course->students()->attach($student->id);
        return [$lecturer, $student, $course, $this->tokenFor($lecturer, 'lecturer-token'), $this->tokenFor($student, 'student-token')];
    }

    private function tokenFor(User $user, string $token): string { $user->update(['api_token_hash' => hash('sha256', $token)]); return $token; }

    private function mindmapPayload(string $requestId): array { return ['topic' => 'Không gian vector', 'difficulty' => 'medium', 'depth' => 3, 'top_k' => 8, 'max_pages' => 5, 'request_id' => $requestId]; }
    private function slidePayload(string $requestId): array { return ['topic' => 'Không gian vector', 'difficulty' => 'medium', 'slide_count' => 5, 'top_k' => 8, 'max_pages' => 5, 'request_id' => $requestId]; }

    private function createDrafts(Course $course, string $token): array
    {
        Queue::fake();
        Http::fake(['*/generate-mindmap' => Http::response($this->generatedMindmap()), '*/generate-slides' => Http::response($this->generatedSlides())]);
        $mindmapResponse = $this->withToken($token)->postJson("/api/courses/{$course->id}/learning-objects/mindmaps", $this->mindmapPayload((string) Str::uuid()))->assertAccepted();
        (new GenerateMindmapLearningObject($mindmapResponse->json('learning_object.generation.id')))->handle(app(RagService::class));
        $slideResponse = $this->withToken($token)->postJson("/api/courses/{$course->id}/learning-objects/slides", $this->slidePayload((string) Str::uuid()))->assertAccepted();
        (new GenerateSlideLearningObject($slideResponse->json('learning_object.generation.id')))->handle(app(RagService::class));
        return [
            LearningObject::with('latestVersion')->findOrFail($mindmapResponse->json('learning_object.id')),
            LearningObject::with('latestVersion')->findOrFail($slideResponse->json('learning_object.id')),
        ];
    }

    private function citation(): array { return ['vector_id' => '5:14:0', 'source_locator' => 'page 5', 'document_id' => 5, 'document_name' => 'chapter.pdf']; }

    private function generatedMindmap(): array
    {
        $citation = [$this->citation()];
        return ['mindmap' => ['title' => 'Không gian vector', 'description' => 'Sơ đồ kiến thức.', 'root' => [
            'id' => 'root', 'label' => 'Không gian vector', 'citations' => $citation, 'children' => [
                ['id' => 'node_1', 'label' => 'Định nghĩa', 'citations' => $citation, 'children' => []],
                ['id' => 'node_2', 'label' => 'Tính chất', 'citations' => $citation, 'children' => []],
            ],
        ], 'markdown' => "# Không gian vector\n## Định nghĩa\n## Tính chất"]];
    }

    private function generatedSlides(): array
    {
        return ['slides' => ['title' => 'Không gian vector', 'description' => 'Bài giảng.', 'slides' => collect(range(1, 5))->map(fn ($index) => [
            'slide_number' => $index, 'title' => "Slide {$index}", 'layout' => $index === 1 ? 'title' : ($index === 5 ? 'summary' : 'bullet_points'),
            'bullet_points' => ['Ý chính một', 'Ý chính hai', 'Ý chính ba'], 'speaker_notes' => 'Ghi chú giảng viên.', 'citations' => [$this->citation()],
        ])->all()]];
    }
}
