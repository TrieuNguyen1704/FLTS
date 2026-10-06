<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LearningObject;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizAttemptController
{
    public function start(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureStudentAccess($request, $course, $learningObject);
        $quiz = $learningObject->quiz()->with('questions.options')->firstOrFail();
        $attempt = $quiz->attempts()->where('student_id', $request->user()->id)->where('status', 'in_progress')->latest()->first();
        if (!$attempt) {
            $nextNumber = ((int) $quiz->attempts()->where('student_id', $request->user()->id)->max('attempt_number')) + 1;
            $attempt = $quiz->attempts()->create([
                'student_id' => $request->user()->id,
                'attempt_number' => $nextNumber,
                'score' => 0,
                'total_correct' => 0,
                'started_at' => now(),
                'status' => 'in_progress',
            ]);
        }
        return response()->json(['attempt' => $this->attemptSummary($attempt), 'quiz' => $this->studentQuiz($learningObject, $quiz)], 201);
    }

    public function submit(Request $request, Course $course, LearningObject $learningObject, QuizAttempt $attempt): JsonResponse
    {
        $this->ensureStudentAccess($request, $course, $learningObject);
        $quiz = $learningObject->quiz()->with('questions.options')->firstOrFail();
        abort_unless($attempt->quiz_id === $quiz->id && $attempt->student_id === $request->user()->id, 404);
        abort_unless($attempt->status === 'in_progress', 422, 'This attempt has already been submitted.');
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.selected_option_id' => ['nullable', 'integer'],
        ]);
        $questions = $quiz->questions;
        $submitted = collect($data['answers'])->keyBy('question_id');
        abort_unless($submitted->count() === $questions->count() && $questions->pluck('id')->diff($submitted->keys())->isEmpty(), 422, 'Submit exactly one answer for every quiz question.');

        $results = [];
        $correctCount = 0;
        DB::transaction(function () use ($attempt, $questions, $submitted, &$results, &$correctCount) {
            foreach ($questions as $question) {
                $selectedId = $submitted->get($question->id)['selected_option_id'] ?? null;
                $selected = $selectedId ? $question->options->firstWhere('id', (int) $selectedId) : null;
                abort_unless($selectedId === null || $selected, 422, 'A selected option does not belong to its question.');
                $correct = $question->options->firstWhere('is_correct', true);
                abort_unless($correct, 422, 'The quiz contains an invalid question without a correct answer.');
                $isCorrect = $selected && $selected->id === $correct->id;
                if ($isCorrect) $correctCount++;
                $attempt->answers()->create([
                    'quiz_question_id' => $question->id,
                    'selected_option_id' => $selected?->id,
                    'is_correct' => (bool) $isCorrect,
                ]);
                $results[] = [
                    'question_id' => $question->id,
                    'selected_option_id' => $selected?->id,
                    'correct_option_id' => $correct->id,
                    'correct_option_index' => $correct->option_index,
                    'is_correct' => (bool) $isCorrect,
                    'explanation' => $question->explanation,
                    'citations' => $question->citations,
                ];
            }
            $score = round(($correctCount / max(1, $questions->count())) * 100, 2);
            $attempt->update([
                'score' => $score,
                'total_correct' => $correctCount,
                'submitted_at' => now(),
                'status' => 'completed',
            ]);
        });

        $bestScore = (float) $quiz->attempts()->where('student_id', $request->user()->id)->where('status', 'completed')->max('score');
        return response()->json([
            'attempt' => $this->attemptSummary($attempt->fresh()),
            'latest_score' => (float) $attempt->fresh()->score,
            'best_score' => $bestScore,
            'results' => $results,
        ]);
    }

    public function history(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureStudentAccess($request, $course, $learningObject);
        $quiz = $learningObject->quiz()->firstOrFail();
        $attempts = $quiz->attempts()->where('student_id', $request->user()->id)->where('status', 'completed')->latest('submitted_at')->get();
        return response()->json([
            'attempts' => $attempts->map(fn ($attempt) => $this->attemptSummary($attempt)),
            'latest_score' => $attempts->isEmpty() ? null : (float) $attempts->first()->score,
            'best_score' => $attempts->isEmpty() ? null : (float) $attempts->max('score'),
        ]);
    }

    private function studentQuiz(LearningObject $object, $quiz): array
    {
        return [
            'learning_object_id' => $object->id,
            'title' => $object->title,
            'description' => $object->description,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'passing_score' => $quiz->passing_score,
            'questions' => $quiz->questions->map(fn ($question) => [
                'id' => $question->id,
                'question_index' => $question->question_index,
                'question_text' => $question->question_text,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'option_index' => $option->option_index,
                    'option_text' => $option->option_text,
                ])->values(),
            ])->values(),
        ];
    }

    private function attemptSummary(QuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'score' => (float) $attempt->score,
            'total_correct' => $attempt->total_correct,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at,
            'submitted_at' => $attempt->submitted_at,
        ];
    }

    private function ensureStudentAccess(Request $request, Course $course, LearningObject $object): void
    {
        abort_unless($object->course_id === $course->id && $object->status === 'published' && $object->type === 'quiz', 404);
        abort_unless($course->students()->where('users.id', $request->user()->id)->exists(), 403);
    }
}
