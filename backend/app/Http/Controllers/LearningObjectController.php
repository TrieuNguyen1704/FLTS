<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFlashcardLearningObject;
use App\Jobs\GenerateMindmapLearningObject;
use App\Jobs\GenerateQuizLearningObject;
use App\Jobs\GenerateSlideLearningObject;
use App\Models\Course;
use App\Models\LearningObject;
use App\Models\LearningObjectGenerationRun;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningObjectController
{
    public function index(Request $request, Course $course): JsonResponse
    {
        $this->ensureCourseAccess($request, $course);
        $query = $course->learningObjects()->with(['creator:id,name', 'quiz', 'latestVersion', 'latestGenerationRun'])->withMax('versions', 'version_number');
        if ($request->user()->role === 'student') {
            $query->where('status', 'published');
        }

        $objects = $query->latest()->get()->map(fn (LearningObject $object) => $this->summary($object));
        return response()->json(['learning_objects' => $objects]);
    }

    public function storeQuiz(Request $request, Course $course): JsonResponse
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
            'request_id' => ['required', 'uuid'],
        ]);
        $documentIds = array_values(array_unique($data['document_ids'] ?? []));
        if ($documentIds !== [] && $course->documents()->whereIn('id', $documentIds)->where('processing_status', 'processed')->count() !== count($documentIds)) {
            return response()->json(['message' => 'Every selected document must belong to this course and be processed.'], 422);
        }

        $existingRun = LearningObjectGenerationRun::with('learningObject')->where('request_id', $data['request_id'])->first();
        if ($existingRun) {
            abort_unless($existingRun->learningObject->course_id === $course->id && $existingRun->learningObject->created_by === $request->user()->id, 409);
            return response()->json(['learning_object' => $this->detail($existingRun->learningObject)], 202);
        }

        $parameters = [
            'topic' => $data['topic'],
            'difficulty' => $data['difficulty'],
            'question_count' => $data['question_count'],
            'document_ids' => $documentIds,
            'top_k' => $data['top_k'] ?? 8,
            'requested_title' => $data['title'] ?? null,
            'requested_description' => $data['description'] ?? null,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'passing_score' => $data['passing_score'] ?? 60,
        ];

        [$learningObject, $run] = DB::transaction(function () use ($request, $course, $data, $parameters) {
            $object = LearningObject::create([
                'course_id' => $course->id,
                'type' => 'quiz',
                'title' => ($data['title'] ?? null) ?: 'Quiz: '.$data['topic'],
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $run = $object->generationRuns()->create([
                'request_id' => $data['request_id'],
                'attempt_number' => 1,
                'status' => 'queued',
                'generation_params' => $parameters,
            ]);
            return [$object, $run];
        });
        $this->dispatchGeneration($learningObject, $run);

        return response()->json(['learning_object' => $this->detail($learningObject->fresh())], 202);
    }

    public function storeFlashcards(Request $request, Course $course): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'topic' => ['required', 'string', 'min:3', 'max:500'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'card_count' => ['required', 'integer', 'min:5', 'max:15'],
            'document_ids' => ['nullable', 'array', 'max:50'],
            'document_ids.*' => ['integer'],
            'top_k' => ['nullable', 'integer', 'min:3', 'max:15'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:10'],
            'request_id' => ['required', 'uuid'],
        ]);
        $documentIds = array_values(array_unique($data['document_ids'] ?? []));
        if ($documentIds !== [] && $course->documents()->whereIn('id', $documentIds)->where('processing_status', 'processed')->count() !== count($documentIds)) {
            return response()->json(['message' => 'Every selected document must belong to this course and be processed.'], 422);
        }

        $existingRun = LearningObjectGenerationRun::with('learningObject')->where('request_id', $data['request_id'])->first();
        if ($existingRun) {
            abort_unless($existingRun->learningObject->course_id === $course->id && $existingRun->learningObject->created_by === $request->user()->id, 409);
            return response()->json(['learning_object' => $this->detail($existingRun->learningObject)], 202);
        }

        $parameters = [
            'topic' => $data['topic'],
            'difficulty' => $data['difficulty'],
            'card_count' => $data['card_count'],
            'document_ids' => $documentIds,
            'top_k' => $data['top_k'] ?? 10,
            'max_pages' => $data['max_pages'] ?? 5,
            'requested_title' => $data['title'] ?? null,
            'requested_description' => $data['description'] ?? null,
        ];

        [$learningObject, $run] = DB::transaction(function () use ($request, $course, $data, $parameters) {
            $object = LearningObject::create([
                'course_id' => $course->id,
                'type' => 'flashcard',
                'title' => ($data['title'] ?? null) ?: 'Flashcard: '.$data['topic'],
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $run = $object->generationRuns()->create([
                'request_id' => $data['request_id'],
                'attempt_number' => 1,
                'status' => 'queued',
                'generation_params' => $parameters,
            ]);
            return [$object, $run];
        });
        $this->dispatchGeneration($learningObject, $run);

        return response()->json(['learning_object' => $this->detail($learningObject->fresh())], 202);
    }

    public function storeMindmap(Request $request, Course $course): JsonResponse
    {
        return $this->storeVersionedLearningObject($request, $course, 'mindmap');
    }

    public function storeSlides(Request $request, Course $course): JsonResponse
    {
        return $this->storeVersionedLearningObject($request, $course, 'slide');
    }

    public function retryGeneration(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureObjectCourse($course, $learningObject);
        $this->ensureOwner($request, $course);
        $latest = $learningObject->generationRuns()->latest('attempt_number')->firstOrFail();
        $hasPersistedContent = $learningObject->type === 'quiz'
            ? $learningObject->quiz()->exists()
            : $learningObject->versions()->exists();
        abort_unless($latest->status === 'failed' && !$hasPersistedContent, 422, 'Only a failed generation can be retried.');

        $run = $learningObject->generationRuns()->create([
            'request_id' => (string) Str::uuid(),
            'attempt_number' => $latest->attempt_number + 1,
            'status' => 'queued',
            'generation_params' => $latest->generation_params,
        ]);
        $this->dispatchGeneration($learningObject, $run);

        return response()->json(['learning_object' => $this->detail($learningObject->fresh())], 202);
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
        if ($learningObject->type === 'flashcard') {
            return $this->updateFlashcards($request, $learningObject);
        }
        if ($learningObject->type === 'mindmap') {
            return $this->updateMindmap($request, $learningObject);
        }
        if ($learningObject->type === 'slide') {
            return $this->updateSlides($request, $learningObject);
        }
        abort_unless($learningObject->type === 'quiz', 422, 'This learning-object type is not editable yet.');
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
        if ($learningObject->type === 'flashcard') {
            $cards = $learningObject->versions()->latest('version_number')->firstOrFail()->content_payload['cards'] ?? [];
            abort_unless(count($cards) >= 3, 422, 'A published Flashcard set requires at least three cards.');
            foreach ($cards as $card) {
                abort_unless(!empty($card['front']) && !empty($card['back']) && !empty($card['citations']), 422, 'Every Flashcard requires front, back, and grounded citations.');
            }
            $learningObject->update(['status' => 'published', 'published_at' => now()]);
            return response()->json(['learning_object' => $this->detail($learningObject->fresh()), 'message' => 'Flashcards published.']);
        }
        if ($learningObject->type === 'mindmap') {
            $root = $learningObject->versions()->latest('version_number')->firstOrFail()->content_payload['root'] ?? null;
            abort_unless(is_array($root) && count($root['children'] ?? []) >= 2 && $this->mindmapIsGrounded($root), 422, 'A published Mindmap requires grounded content and at least two main branches.');
            $learningObject->update(['status' => 'published', 'published_at' => now()]);
            return response()->json(['learning_object' => $this->detail($learningObject->fresh()), 'message' => 'Mindmap published.']);
        }
        if ($learningObject->type === 'slide') {
            $slides = $learningObject->versions()->latest('version_number')->firstOrFail()->content_payload['slides'] ?? [];
            abort_unless(count($slides) >= 5, 422, 'A published slide deck requires at least five slides.');
            foreach ($slides as $slide) {
                abort_unless(!empty($slide['title']) && count($slide['bullet_points'] ?? []) >= 3 && !empty($slide['speaker_notes']) && !empty($slide['citations']), 422, 'Every slide requires content, speaker notes, and grounded citations.');
            }
            $learningObject->update(['status' => 'published', 'published_at' => now()]);
            return response()->json(['learning_object' => $this->detail($learningObject->fresh()), 'message' => 'Slides published.']);
        }
        abort_unless($learningObject->type === 'quiz', 422, 'This learning-object type cannot be published yet.');
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

    public function destroy(Request $request, Course $course, LearningObject $learningObject): JsonResponse
    {
        $this->ensureObjectCourse($course, $learningObject);
        $this->ensureOwner($request, $course);

        DB::transaction(function () use ($learningObject) {
            $learningObject->delete();
        });

        return response()->json(['message' => 'Đã xóa học liệu thành công.']);
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
        $latestPayload = $object->latestVersion?->content_payload
            ?? $object->versions()->latest('version_number')->first()?->content_payload
            ?? [];
        return [
            'id' => $object->id, 'course_id' => $object->course_id, 'type' => $object->type,
            'title' => $object->title, 'description' => $object->description, 'status' => $object->status,
            'published_at' => $object->published_at, 'created_at' => $object->created_at,
            'creator' => $object->creator, 'total_questions' => $object->quiz?->total_questions,
            'total_cards' => $object->type === 'flashcard' ? count($latestPayload['cards'] ?? []) : null,
            'total_nodes' => $object->type === 'mindmap' ? $this->countMindmapNodes($latestPayload['root'] ?? null) : null,
            'total_slides' => $object->type === 'slide' ? count($latestPayload['slides'] ?? []) : null,
            'current_version' => (int) ($object->versions_max_version_number ?? $object->versions()->max('version_number')),
            'generation' => $this->generationSummary($object->latestGenerationRun ?? $object->generationRuns()->latest('attempt_number')->first()),
        ];
    }

    private function detail(LearningObject $object, bool $studentView = false): array
    {
        $object->load(['creator:id,name', 'versions' => fn ($query) => $query->latest('version_number'), 'latestVersion', 'quiz.questions.options', 'latestGenerationRun']);
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
        $data['flashcard'] = $object->type === 'flashcard' && $object->latestVersion
            ? $object->latestVersion->content_payload
            : null;
        $data['mindmap'] = $object->type === 'mindmap' && $object->latestVersion
            ? $object->latestVersion->content_payload
            : null;
        $data['slides'] = $object->type === 'slide' && $object->latestVersion
            ? $this->slidePayload($object->latestVersion->content_payload, $studentView)
            : null;
        return $data;
    }

    private function updateFlashcards(Request $request, LearningObject $learningObject): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cards' => ['required', 'array', 'min:3', 'max:50'],
            'cards.*.front' => ['required', 'string', 'min:1', 'max:2000'],
            'cards.*.back' => ['required', 'string', 'min:1', 'max:4000'],
            'cards.*.hint' => ['nullable', 'string', 'max:2000'],
            'cards.*.citations' => ['required', 'array', 'min:1'],
            'cards.*.citations.*.vector_id' => ['required', 'string', 'max:255'],
            'cards.*.citations.*.source_locator' => ['nullable', 'string', 'max:255'],
            'cards.*.citations.*.document_id' => ['nullable', 'integer'],
            'cards.*.citations.*.document_name' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $learningObject, $data) {
            $learningObject->update(['title' => $data['title'], 'description' => $data['description'] ?? null]);
            $previous = $learningObject->versions()->latest('version_number')->first();
            $cards = collect($data['cards'])->values()->map(fn (array $card, int $index) => [
                'id' => $index + 1,
                'front' => $card['front'],
                'back' => $card['back'],
                'hint' => $card['hint'] ?? '',
                'citations' => array_values($card['citations']),
            ])->all();
            $learningObject->versions()->create([
                'version_number' => ((int) $previous?->version_number) + 1,
                'content_payload' => ['cards' => $cards],
                'generation_params' => $previous?->generation_params ?? [],
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json(['learning_object' => $this->detail($learningObject->fresh())]);
    }

    private function dispatchGeneration(LearningObject $learningObject, LearningObjectGenerationRun $run): void
    {
        match ($learningObject->type) {
            'flashcard' => GenerateFlashcardLearningObject::dispatch($run->id),
            'mindmap' => GenerateMindmapLearningObject::dispatch($run->id),
            'slide' => GenerateSlideLearningObject::dispatch($run->id),
            default => GenerateQuizLearningObject::dispatch($run->id),
        };
    }

    private function storeVersionedLearningObject(Request $request, Course $course, string $type): JsonResponse
    {
        $this->ensureOwner($request, $course);
        $typeRules = $type === 'mindmap'
            ? ['depth' => ['required', 'integer', 'min:2', 'max:4']]
            : ['slide_count' => ['required', 'integer', 'min:5', 'max:10']];
        $data = $request->validate(array_merge([
            'title' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'topic' => ['required', 'string', 'min:3', 'max:500'], 'difficulty' => ['required', 'in:easy,medium,hard'],
            'document_ids' => ['nullable', 'array', 'max:50'], 'document_ids.*' => ['integer'],
            'top_k' => ['nullable', 'integer', 'min:3', 'max:15'], 'max_pages' => ['nullable', 'integer', 'min:1', 'max:10'],
            'request_id' => ['required', 'uuid'],
        ], $typeRules));
        $documentIds = array_values(array_unique($data['document_ids'] ?? []));
        if ($documentIds !== [] && $course->documents()->whereIn('id', $documentIds)->where('processing_status', 'processed')->count() !== count($documentIds)) {
            return response()->json(['message' => 'Every selected document must belong to this course and be processed.'], 422);
        }
        $existingRun = LearningObjectGenerationRun::with('learningObject')->where('request_id', $data['request_id'])->first();
        if ($existingRun) {
            abort_unless($existingRun->learningObject->course_id === $course->id && $existingRun->learningObject->created_by === $request->user()->id, 409);
            return response()->json(['learning_object' => $this->detail($existingRun->learningObject)], 202);
        }
        $parameters = [
            'topic' => $data['topic'], 'difficulty' => $data['difficulty'], 'document_ids' => $documentIds,
            'top_k' => $data['top_k'] ?? 10, 'max_pages' => $data['max_pages'] ?? 5,
            'requested_title' => $data['title'] ?? null, 'requested_description' => $data['description'] ?? null,
        ];
        $parameters[$type === 'mindmap' ? 'depth' : 'slide_count'] = $data[$type === 'mindmap' ? 'depth' : 'slide_count'];
        [$object, $run] = DB::transaction(function () use ($request, $course, $data, $parameters, $type) {
            $label = $type === 'mindmap' ? 'Mindmap' : 'Slides';
            $object = LearningObject::create([
                'course_id' => $course->id, 'type' => $type, 'title' => ($data['title'] ?? null) ?: $label.': '.$data['topic'],
                'description' => $data['description'] ?? null, 'status' => 'draft', 'created_by' => $request->user()->id,
            ]);
            $run = $object->generationRuns()->create(['request_id' => $data['request_id'], 'attempt_number' => 1, 'status' => 'queued', 'generation_params' => $parameters]);
            return [$object, $run];
        });
        $this->dispatchGeneration($object, $run);
        return response()->json(['learning_object' => $this->detail($object->fresh())], 202);
    }

    private function updateMindmap(Request $request, LearningObject $learningObject): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'root' => ['required', 'array'], 'root.id' => ['required', 'string'], 'root.label' => ['required', 'string', 'max:500'],
            'root.children' => ['required', 'array', 'min:2'],
        ]);
        $previous = $learningObject->versions()->latest('version_number')->firstOrFail();
        $root = $this->mergeMindmapLabels($data['root'], $previous->content_payload['root']);
        DB::transaction(function () use ($request, $learningObject, $data, $previous, $root) {
            $learningObject->update(['title' => $data['title'], 'description' => $data['description'] ?? null]);
            $learningObject->versions()->create([
                'version_number' => $previous->version_number + 1,
                'content_payload' => ['root' => $root, 'markdown' => $this->mindmapMarkdown($root)],
                'generation_params' => $previous->generation_params ?? [], 'created_by' => $request->user()->id,
            ]);
        });
        return response()->json(['learning_object' => $this->detail($learningObject->fresh())]);
    }

    private function updateSlides(Request $request, LearningObject $learningObject): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'slides' => ['required', 'array', 'min:5', 'max:10'], 'slides.*.slide_number' => ['required', 'integer', 'min:1'],
            'slides.*.title' => ['required', 'string', 'max:500'], 'slides.*.layout' => ['required', 'in:title,bullet_points,summary'],
            'slides.*.bullet_points' => ['required', 'array', 'min:3', 'max:5'], 'slides.*.bullet_points.*' => ['required', 'string', 'max:1000'],
            'slides.*.speaker_notes' => ['required', 'string', 'max:4000'],
        ]);
        $previous = $learningObject->versions()->latest('version_number')->firstOrFail();
        $previousSlides = collect($previous->content_payload['slides'] ?? [])->keyBy('slide_number');
        $slides = collect($data['slides'])->values()->map(function (array $slide, int $index) use ($previousSlides) {
            $old = $previousSlides->get($slide['slide_number']);
            abort_unless($old, 422, 'Slide structure cannot be replaced during editing.');
            return [
                'slide_number' => $index + 1, 'title' => $slide['title'], 'layout' => $slide['layout'],
                'bullet_points' => array_values($slide['bullet_points']), 'speaker_notes' => $slide['speaker_notes'],
                'citations' => array_values($old['citations'] ?? []),
            ];
        })->all();
        DB::transaction(function () use ($request, $learningObject, $data, $previous, $slides) {
            $learningObject->update(['title' => $data['title'], 'description' => $data['description'] ?? null]);
            $learningObject->versions()->create([
                'version_number' => $previous->version_number + 1, 'content_payload' => ['slides' => $slides],
                'generation_params' => $previous->generation_params ?? [], 'created_by' => $request->user()->id,
            ]);
        });
        return response()->json(['learning_object' => $this->detail($learningObject->fresh())]);
    }

    private function mergeMindmapLabels(array $submitted, array $original): array
    {
        abort_unless(($submitted['id'] ?? null) === ($original['id'] ?? null), 422, 'Mindmap structure cannot be replaced during editing.');
        $submittedChildren = array_values($submitted['children'] ?? []);
        $originalChildren = array_values($original['children'] ?? []);
        abort_unless(count($submittedChildren) === count($originalChildren), 422, 'Mindmap structure cannot be replaced during editing.');
        $label = trim((string) ($submitted['label'] ?? ''));
        abort_unless($label !== '' && mb_strlen($label) <= 500, 422, 'Every mindmap node requires a valid label.');
        return [
            'id' => $original['id'], 'label' => $label, 'citations' => array_values($original['citations'] ?? []),
            'children' => array_map(fn ($child, $index) => $this->mergeMindmapLabels($child, $originalChildren[$index]), $submittedChildren, array_keys($submittedChildren)),
        ];
    }

    private function mindmapMarkdown(array $node, int $level = 1): string
    {
        $lines = [str_repeat('#', min($level, 6)).' '.$node['label']];
        foreach ($node['children'] ?? [] as $child) $lines[] = $this->mindmapMarkdown($child, $level + 1);
        return implode("\n", $lines);
    }

    private function mindmapIsGrounded(array $node): bool
    {
        if (empty($node['label']) || empty($node['citations'])) return false;
        foreach ($node['children'] ?? [] as $child) if (!$this->mindmapIsGrounded($child)) return false;
        return true;
    }

    private function countMindmapNodes(?array $node): int
    {
        if (!$node) return 0;
        return 1 + array_sum(array_map(fn ($child) => $this->countMindmapNodes($child), $node['children'] ?? []));
    }

    private function slidePayload(array $payload, bool $studentView): array
    {
        if (!$studentView) return $payload;
        $payload['slides'] = array_map(function (array $slide) {
            unset($slide['speaker_notes']);
            return $slide;
        }, $payload['slides'] ?? []);
        return $payload;
    }

    private function generationSummary(?LearningObjectGenerationRun $run): ?array
    {
        if (!$run) {
            return null;
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'attempt_number' => $run->attempt_number,
            'error_code' => $run->error_code,
            'error_message' => $run->error_message,
            'started_at' => $run->started_at,
            'finished_at' => $run->finished_at,
        ];
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
