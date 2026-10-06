<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearningObject;
use App\Models\QuizOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SprintThreeQuizApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_owner_generates_a_grounded_draft_and_persists_parameters_and_version(): void
    {
        [$lecturer, , $course, $lecturerToken] = $this->courseActors();
        Http::fake(['*' => Http::response($this->generatedQuiz(), 200)]);

        $response = $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/quizzes", [
            'title' => 'RAG Knowledge Check',
            'topic' => 'retrieval augmented generation',
            'difficulty' => 'medium',
            'question_count' => 3,
            'document_ids' => [],
            'top_k' => 8,
            'passing_score' => 70,
        ])->assertCreated()
            ->assertJsonPath('learning_object.status', 'draft')
            ->assertJsonPath('learning_object.quiz.total_questions', 3)
            ->assertJsonPath('learning_object.quiz.questions.0.citations.0.vector_id', '5:14:0');

        $objectId = $response->json('learning_object.id');
        $this->assertDatabaseHas('learning_objects', ['id' => $objectId, 'course_id' => $course->id, 'created_by' => $lecturer->id, 'type' => 'quiz', 'status' => 'draft']);
        $this->assertDatabaseHas('learning_object_versions', ['learning_object_id' => $objectId, 'version_number' => 1]);
        $this->assertDatabaseCount('quiz_questions', 3);
        $this->assertDatabaseCount('quiz_options', 12);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/internal/v1/generation/quiz')
            && $request['course_id'] === $course->id
            && $request['question_count'] === 3
            && $request['difficulty'] === 'medium');
    }

    public function test_non_owner_and_student_cannot_generate_quizzes(): void
    {
        [, $student, $course, , $studentToken] = $this->courseActors();
        $other = User::create(['name' => 'Other lecturer', 'email' => 'other@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $otherToken = $this->tokenFor($other, 'other-token');
        $payload = ['topic' => 'grounded topic', 'difficulty' => 'easy', 'question_count' => 3, 'top_k' => 3];

        $this->withToken($otherToken)->postJson("/api/courses/{$course->id}/learning-objects/quizzes", $payload)->assertForbidden();
        $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/quizzes", $payload)->assertForbidden();
        $this->assertDatabaseCount('learning_objects', 0);
        Http::assertNothingSent();
    }

    public function test_lecturer_edit_creates_a_new_version_and_published_quiz_is_immutable(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        $object = $this->createDraft($course, $lecturerToken);
        $payload = $this->editablePayload('Edited question');

        $this->withToken($lecturerToken)->patchJson("/api/courses/{$course->id}/learning-objects/{$object->id}", $payload)
            ->assertOk()
            ->assertJsonPath('learning_object.quiz.questions.0.question_text', 'Edited question')
            ->assertJsonPath('learning_object.current_version', 2);
        $this->assertDatabaseHas('learning_object_versions', ['learning_object_id' => $object->id, 'version_number' => 2]);

        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/publish")
            ->assertOk()->assertJsonPath('learning_object.status', 'published');
        $this->withToken($lecturerToken)->patchJson("/api/courses/{$course->id}/learning-objects/{$object->id}", $payload)
            ->assertUnprocessable();
    }

    public function test_students_only_receive_published_quizzes_without_answer_keys(): void
    {
        [, , $course, $lecturerToken, $studentToken] = $this->courseActors();
        $object = $this->createDraft($course, $lecturerToken);

        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects")->assertOk()->assertJsonCount(0, 'learning_objects');
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$object->id}")->assertNotFound();
        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/publish")->assertOk();

        $response = $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$object->id}")
            ->assertOk()->assertJsonPath('learning_object.status', 'published');
        $this->assertArrayNotHasKey('is_correct', $response->json('learning_object.quiz.questions.0.options.0'));
        $this->assertArrayNotHasKey('explanation', $response->json('learning_object.quiz.questions.0'));
        $this->assertArrayNotHasKey('citations', $response->json('learning_object.quiz.questions.0'));
    }

    public function test_student_submits_quiz_receives_grounded_feedback_and_can_retake(): void
    {
        [, $student, $course, $lecturerToken, $studentToken] = $this->courseActors();
        $object = $this->createDraft($course, $lecturerToken);
        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/publish")->assertOk();

        $start = $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/quiz-attempts")
            ->assertCreated()->assertJsonPath('attempt.attempt_number', 1);
        $quiz = $object->fresh()->quiz()->with('questions.options')->firstOrFail();
        $answers = $quiz->questions->map(fn ($question, $index) => [
            'question_id' => $question->id,
            'selected_option_id' => $index < 2
                ? $question->options->firstWhere('is_correct', true)->id
                : $question->options->firstWhere('is_correct', false)->id,
        ])->values()->all();
        $attemptId = $start->json('attempt.id');

        $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/quiz-attempts/{$attemptId}/submit", ['answers' => $answers])
            ->assertOk()
            ->assertJsonPath('attempt.total_correct', 2)
            ->assertJsonPath('latest_score', 66.67)
            ->assertJsonPath('best_score', 66.67)
            ->assertJsonPath('results.0.citations.0.vector_id', '5:14:0');
        $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'student_id' => $student->id, 'attempt_number' => 1, 'status' => 'completed']);

        $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/quiz-attempts")
            ->assertCreated()->assertJsonPath('attempt.attempt_number', 2);
        $this->withToken($studentToken)->getJson("/api/courses/{$course->id}/learning-objects/{$object->id}/quiz-attempts")
            ->assertOk()->assertJsonPath('latest_score', 66.67)->assertJsonPath('best_score', 66.67);
    }

    public function test_submission_rejects_an_option_from_another_question(): void
    {
        [, , $course, $lecturerToken, $studentToken] = $this->courseActors();
        $object = $this->createDraft($course, $lecturerToken);
        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/publish")->assertOk();
        $attemptId = $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/quiz-attempts")->json('attempt.id');
        $quiz = $object->fresh()->quiz()->with('questions.options')->firstOrFail();
        $foreignOption = $quiz->questions[1]->options[0]->id;
        $answers = $quiz->questions->map(fn ($question) => ['question_id' => $question->id, 'selected_option_id' => $foreignOption])->all();

        $this->withToken($studentToken)->postJson("/api/courses/{$course->id}/learning-objects/{$object->id}/quiz-attempts/{$attemptId}/submit", ['answers' => $answers])
            ->assertUnprocessable();
        $this->assertDatabaseCount('quiz_attempt_answers', 0);
    }

    public function test_quiz_generation_maps_provider_failures_to_safe_public_errors(): void
    {
        [, , $course, $lecturerToken] = $this->courseActors();
        Http::fake(['*' => Http::response(['detail' => ['code' => 'QUIZ_GENERATION_FAILED', 'message' => 'private provider detail', 'stage' => 'generation']], 502)]);

        $this->withToken($lecturerToken)->postJson("/api/courses/{$course->id}/learning-objects/quizzes", [
            'topic' => 'grounded topic', 'difficulty' => 'medium', 'question_count' => 3, 'top_k' => 3,
        ])->assertStatus(503)->assertJsonPath('message', 'Quiz generation is temporarily unavailable.')
            ->assertJsonMissing(['private provider detail']);
    }

    private function courseActors(): array
    {
        $lecturer = User::create(['name' => 'Lecturer', 'email' => 'lecturer3@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'lecturer']);
        $student = User::create(['name' => 'Student', 'email' => 'student3@flts.test', 'password' => bcrypt('Password123!'), 'role' => 'student']);
        $course = Course::create(['name' => 'Sprint 3 Course', 'code' => 'SPR3', 'description' => 'Quiz course', 'lecturer_id' => $lecturer->id]);
        $course->students()->attach($student->id);
        return [$lecturer, $student, $course, $this->tokenFor($lecturer, 'lecturer-token'), $this->tokenFor($student, 'student-token')];
    }

    private function tokenFor(User $user, string $token): string
    {
        $user->update(['api_token_hash' => hash('sha256', $token)]);
        return $token;
    }

    private function createDraft(Course $course, string $token): LearningObject
    {
        Http::fake(['*' => Http::response($this->generatedQuiz(), 200)]);
        $id = $this->withToken($token)->postJson("/api/courses/{$course->id}/learning-objects/quizzes", [
            'topic' => 'retrieval augmented generation', 'difficulty' => 'medium', 'question_count' => 3, 'top_k' => 3,
        ])->assertCreated()->json('learning_object.id');
        return LearningObject::findOrFail($id);
    }

    private function generatedQuiz(): array
    {
        return [
            'quiz' => [
                'title' => 'Generated RAG Quiz',
                'description' => 'Three grounded questions.',
                'questions' => collect(range(1, 3))->map(fn ($index) => [
                    'question' => "Generated question {$index}?",
                    'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
                    'correct_index' => ($index - 1) % 4,
                    'explanation' => 'This answer is supported by the retrieved source.',
                    'citations' => [[
                        'vector_id' => '5:14:0', 'source_locator' => 'page 5',
                        'document_id' => 5, 'document_name' => 'chapter.pdf',
                    ]],
                ])->all(),
            ],
            'retrieval' => ['match_count' => 1],
            'generation_model' => 'gemini-2.5-flash',
        ];
    }

    private function editablePayload(string $firstQuestion): array
    {
        return [
            'title' => 'Edited quiz',
            'description' => 'Lecturer-reviewed content',
            'passing_score' => 60,
            'time_limit_minutes' => null,
            'questions' => collect(range(1, 3))->map(fn ($index) => [
                'question_text' => $index === 1 ? $firstQuestion : "Edited question {$index}",
                'options' => ['A', 'B', 'C', 'D'],
                'correct_index' => 0,
                'explanation' => 'Grounded explanation.',
                'citations' => [['vector_id' => '5:14:0', 'source_locator' => 'page 5', 'document_id' => 5, 'document_name' => 'chapter.pdf']],
            ])->all(),
        ];
    }
}
