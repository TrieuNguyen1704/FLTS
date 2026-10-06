<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LearningObject;
use App\Models\Quiz;
use App\Services\RagService;
use App\Services\RagServiceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearningObjectController
{
    public function index(Request $request, Course $course): JsonResponse
    {
        $this->ensureCourseAccess($request, $course);
        $query = $course->learningObjects()->with(['creator:id,name', 'quiz'])->withMax('versions', 'version_number');
        if ($request->user()->role === 'student') {
            $query->where('status', 'published');
        }

        $objects = $query->latest()->get()->map(fn (LearningObject $object) => $this->summary($object));
        return response()->json(['learning_objects' => $objects]);
    }

    public function storeQuiz(Request $request, Course $course, RagService $rag): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'topic' => ['required', 'string', 'min:3', 'max:500'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_count' => ['required', 'integer', 'min:3', 'max:20'],
            'document_ids' => ['nullable', 'array', 'max:50'],
            'document_ids.*' => ['integer'],
            'top_k' => ['nullable', 'integer', 'min:3', 'max:15'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $documentIds = array_values(array_unique($data['document_ids'] ?? []));
        if ($documentIds !== [] && $course->documents()->whereIn('id', $documentIds)->where('processing_status', 'processed')->count() !== count($documentIds)) {
            return response()->json(['message' => 'Every selected document must belong to this course and be processed.'], 422);
        }

        $parameters = [
            'topic' => $data['topic'],
            'difficulty' => $data['difficulty'],
            'question_count' => $data['question_count'],
            'document_ids' => $documentIds,
            'top_k' => $data['top_k'] ?? 8,
        ];
        try {
            $generated = $rag->generateQuiz($course, $parameters);
        } catch (RagServiceException $exception) {
            $status = in_array($exception->getCode(), [422, 429], true) ? $exception->getCode() : 503;
            $message = $status === 503 ? 'Quiz generation is temporarily unavailable.' : $exception->getMessage();
            return response()->json(['message' => $message], $status);
        }

        $learningObject = DB::transaction(function () use ($request, $course, $data, $parameters, $generated) {
            $payload = $generated['quiz'];
            $object = LearningObject::create([
                'course_id' => $course->id,
                'type' => 'quiz',
                'title' => $data['title'] ?? $payload['title'],
                'description' => $data['description'] ?? ($payload['description'] ?? null),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $quiz = $object->quiz()->create([
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'passing_score' => $data['passing_score'] ?? 60,
                'total_questions' => count($payload['questions']),
            ]);
            $this->replaceQuestions($quiz, $payload['questions']);
            $object->versions()->create([
                'version_number' => 1,
                'content_payload' => $this->contentSnapshot($quiz->fresh('questions.options')),
                'generation_params' => $parameters,
                'created_by' => $request->user()->id,
            ]);
            return $object;
        });

        return response()->json(['learning_object' => $this->detail($learningObject->fresh())], 201);
    }

    public function show(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureObjectCourse($course, $learningObject);
        $this->ensureCourseAccess($request, $course);
        if ($request->user()->role === 'student') {
            abort_unless($learningObject->status === 'published', 404);
        }
        return response()->json(['learning_object' => $this->detail($learningObject, $request->user()->role === 'student')]);
    }

    public function update(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureObjectCourse($course, $learningObject);
        $this->ensureOwner($request, $course);
        abort_unless($learningObject->status === 'draft', 422, 'Only draft learning objects can be edited.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'questions' => ['required', 'array', 'min:3', 'max:20'],
            'questions.*.question_text' => ['required', 'string', 'min:3', 'max:2000'],
            'questions.*.explanation' => ['required', 'string', 'min:3', 'max:4000'],
            'questions.*.options' => ['required', 'array', 'size:4'],
            'questions.*.options.*' => ['required', 'string', 'min:1', 'max:1000'],
            'questions.*.correct_index' => ['required', 'integer', 'min:0', 'max:3'],
            'questions.*.citations' => ['required', 'array', 'min:1'],
            'questions.*.citations.*.vector_id' => ['required', 'string', 'max:255'],
            'questions.*.citations.*.source_locator' => ['nullable', 'string', 'max:255'],
            'questions.*.citations.*.document_id' => ['nullable', 'integer'],
            'questions.*.citations.*.document_name' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $learningObject, $data) {
            $learningObject->update(['title' => $data['title'], 'description' => $data['description'] ?? null]);
            $quiz = $learningObject->quiz()->firstOrFail();
            $quiz->update([
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'passing_score' => $data['passing_score'],
                'total_questions' => count($data['questions']),
            ]);
            $this->replaceQuestions($quiz, $data['questions']);
            $previous = $learningObject->versions()->latest('version_number')->first();
            $learningObject->versions()->create([
                'version_number' => ((int) $previous?->version_number) + 1,
                'content_payload' => $this->contentSnapshot($quiz->fresh('questions.options')),
                'generation_params' => $previous?->generation_params ?? [],
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json(['learning_object' => $this->detail($learningObject->fresh())]);
    }

    public function publish(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureObjectCourse($course, $learningObject);
        $this->ensureOwner($request, $course);
        abort_unless($learningObject->status === 'draft', 422, 'Only a draft can be published.');
        $quiz = $learningObject->quiz()->with('questions.options')->firstOrFail();
        abort_unless($quiz->questions->count() >= 3, 422, 'A published quiz requires at least three questions.');
        foreach ($quiz->questions as $question) {
            abort_unless($question->options->count() === 4 && $question->options->where('is_correct', true)->count() === 1, 422, 'Every question requires four options and one correct answer.');
            abort_unless(!empty($question->explanation) && !empty($question->citations), 422, 'Every published question requires an explanation and grounded citation.');
        }
        $learningObject->update(['status' => 'published', 'published_at' => now()]);
        return response()->json(['learning_object' => $this->detail($learningObject->fresh()), 'message' => 'Quiz published.']);
    }

    public function archive(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureObjectCourse($course, $learningObject);
        $this->ensureOwner($request, $course);
        abort_if($learningObject->status === 'archived', 422, 'This learning object is already archived.');
        $learningObject->update(['status' => 'archived']);
        return response()->json(['learning_object' => $this->detail($learningObject->fresh()), 'message' => 'Learning object archived.']);
    }

    private function replaceQuestions(Quiz $quiz, array $questions): void
    {
        $quiz->questions()->delete();
        foreach (array_values($questions) as $questionIndex => $questionData) {
            $question = $quiz->questions()->create([
                'question_index' => $questionIndex,
                'question_text' => $questionData['question_text'] ?? $questionData['question'],
                'question_type' => 'single_choice',
                'explanation' => $questionData['explanation'],
                'citations' => $questionData['citations'],
            ]);
            foreach (array_values($questionData['options']) as $optionIndex => $optionText) {
                $question->options()->create([
                    'option_index' => $optionIndex,
                    'option_text' => $optionText,
                    'is_correct' => $optionIndex === (int) $questionData['correct_index'],
                ]);
            }
        }
    }

    private function contentSnapshot(Quiz $quiz): array
    {
        return [
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'passing_score' => $quiz->passing_score,
            'questions' => $quiz->questions->map(fn ($question) => [
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'explanation' => $question->explanation,
                'citations' => $question->citations,
                'options' => $question->options->map(fn ($option) => [
                    'option_text' => $option->option_text,
                    'is_correct' => $option->is_correct,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function summary(LearningObject $object): array
    {
        return [
            'id' => $object->id, 'course_id' => $object->course_id, 'type' => $object->type,
            'title' => $object->title, 'description' => $object->description, 'status' => $object->status,
            'published_at' => $object->published_at, 'created_at' => $object->created_at,
            'creator' => $object->creator, 'total_questions' => $object->quiz?->total_questions,
            'current_version' => (int) ($object->versions_max_version_number ?? $object->versions()->max('version_number')),
        ];
    }

    private function detail(LearningObject $object, bool $studentView = false): array
    {
        $object->load(['creator:id,name', 'versions' => fn ($query) => $query->latest('version_number'), 'quiz.questions.options']);
        $quiz = $object->quiz;
        $data = $this->summary($object);
        $data['versions'] = $object->versions->map(fn ($version) => [
            'id' => $version->id, 'version_number' => $version->version_number,
            'created_by' => $version->created_by, 'created_at' => $version->created_at,
        ])->values();
        $data['quiz'] = $quiz ? [
            'id' => $quiz->id,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'passing_score' => $quiz->passing_score,
            'total_questions' => $quiz->total_questions,
            'questions' => $quiz->questions->map(function ($question) use ($studentView) {
                $questionData = [
                    'id' => $question->id,
                    'question_index' => $question->question_index,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'options' => $question->options->map(function ($option) use ($studentView) {
                        $optionData = ['id' => $option->id, 'option_index' => $option->option_index, 'option_text' => $option->option_text];
                        if (!$studentView) $optionData['is_correct'] = $option->is_correct;
                        return $optionData;
                    })->values(),
                ];
                if (!$studentView) {
                    $questionData['explanation'] = $question->explanation;
                    $questionData['citations'] = $question->citations;
                }
                return $questionData;
            })->values(),
        ] : null;
        return $data;
    }

    private function ensureObjectCourse(Course $course, LearningObject $object): void
    {
        abort_unless($object->course_id === $course->id, 404);
    }

    private function ensureOwner(Request $request, Course $course): void
    {
        abort_unless($request->user()->role === 'lecturer' && $course->lecturer_id === $request->user()->id, 403);
    }

    private function ensureCourseAccess(Request $request, Course $course): void
    {
        $user = $request->user();
        if ($user->role === 'lecturer') {
            $this->ensureOwner($request, $course);
            return;
        }
        abort_unless($user->role === 'student' && $course->students()->where('users.id', $user->id)->exists(), 403);
    }
}
