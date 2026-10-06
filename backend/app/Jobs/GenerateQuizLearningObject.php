<?php

namespace App\Jobs;

use App\Models\LearningObjectGenerationRun;
use App\Models\Quiz;
use App\Services\RagService;
use App\Services\RagServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateQuizLearningObject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;
    public bool $failOnTimeout = true;

    public function __construct(public int $runId)
    {
        // Quiz generation is interactive work and must never wait behind long document ingestion jobs.
        $this->onQueue('generation');
    }

    public function handle(RagService $rag): void
    {
        $run = LearningObjectGenerationRun::with('learningObject.course')->findOrFail($this->runId);
        if ($run->status !== 'queued') {
            return;
        }

        $run->update([
            'status' => 'generating',
            'started_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ]);

        try {
            $generated = $rag->generateQuiz($run->learningObject->course, $run->generation_params);
            $this->persistResult($run, $generated);
        } catch (RagServiceException $exception) {
            $this->markFailed(
                $run,
                $exception->getCode() === 429 ? 'RATE_LIMITED' : ($exception->getCode() === 422 ? 'NO_CONTEXT' : 'SERVICE_UNAVAILABLE'),
                $exception->getCode() === 429
                    ? 'Dịch vụ tạo câu hỏi đang bận. Vui lòng thử lại sau ít phút.'
                    : ($exception->getCode() === 422
                        ? 'Không tìm thấy đủ nội dung phù hợp trong tài liệu đã chọn.'
                        : 'Chưa thể tạo Quiz lúc này. Vui lòng thử lại sau.')
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($run, 'GENERATION_FAILED', 'Chưa thể tạo Quiz lúc này. Vui lòng thử lại sau.');
        }
    }

    private function persistResult(LearningObjectGenerationRun $run, array $generated): void
    {
        $payload = $generated['quiz'] ?? null;
        if (!is_array($payload) || empty($payload['questions']) || !is_array($payload['questions'])) {
            throw new \RuntimeException('AI service returned an incomplete quiz result.');
        }

        DB::transaction(function () use ($run, $payload) {
            $lockedRun = LearningObjectGenerationRun::lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->status !== 'generating') {
                return;
            }

            $parameters = $lockedRun->generation_params;
            $object = $lockedRun->learningObject()->lockForUpdate()->firstOrFail();
            $object->update([
                'title' => $parameters['requested_title'] ?: $payload['title'],
                'description' => $parameters['requested_description'] ?: ($payload['description'] ?? null),
            ]);
            $quiz = $object->quiz()->create([
                'time_limit_minutes' => $parameters['time_limit_minutes'] ?? null,
                'passing_score' => $parameters['passing_score'] ?? 60,
                'total_questions' => count($payload['questions']),
            ]);
            $this->replaceQuestions($quiz, $payload['questions']);
            $object->versions()->create([
                'version_number' => 1,
                'content_payload' => $this->contentSnapshot($quiz->fresh('questions.options')),
                'generation_params' => collect($parameters)->except(['requested_title', 'requested_description', 'time_limit_minutes', 'passing_score'])->all(),
                'created_by' => $object->created_by,
            ]);
            $lockedRun->update(['status' => 'completed', 'finished_at' => now()]);
        });
    }

    private function replaceQuestions(Quiz $quiz, array $questions): void
    {
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

    private function markFailed(LearningObjectGenerationRun $run, string $code, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'error_code' => $code,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = LearningObjectGenerationRun::find($this->runId);
        if ($run && in_array($run->status, ['queued', 'generating'], true)) {
            $this->markFailed($run, 'GENERATION_TIMEOUT', 'Quá trình tạo Quiz mất quá nhiều thời gian. Vui lòng thử lại.');
        }
    }
}
