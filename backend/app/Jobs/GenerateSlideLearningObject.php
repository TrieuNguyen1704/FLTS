<?php

namespace App\Jobs;

use App\Models\LearningObjectGenerationRun;
use App\Services\RagService;
use App\Services\RagServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateSlideLearningObject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;
    public bool $failOnTimeout = true;

    public function __construct(public int $runId) { $this->onQueue('generation'); }

    public function handle(RagService $rag): void
    {
        $run = LearningObjectGenerationRun::with('learningObject.course')->findOrFail($this->runId);
        if ($run->status !== 'queued' || $run->learningObject?->type !== 'slide') return;
        $run->update(['status' => 'generating', 'started_at' => now(), 'error_code' => null, 'error_message' => null]);
        try {
            $generated = $rag->generateSlides($run->learningObject->course, $run->generation_params);
            $this->persistResult($run, $generated);
        } catch (RagServiceException $exception) {
            $this->markFailed($run, $exception->getCode() === 429 ? 'RATE_LIMITED' : ($exception->getCode() === 422 ? 'NO_CONTEXT' : 'SERVICE_UNAVAILABLE'),
                $exception->getCode() === 429 ? 'Dịch vụ tạo Slide đang bận. Vui lòng thử lại sau ít phút.'
                    : ($exception->getCode() === 422 ? 'Không tìm thấy đủ nội dung phù hợp trong tài liệu đã chọn.' : 'Chưa thể tạo Slide lúc này. Vui lòng thử lại sau.'));
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($run, 'GENERATION_FAILED', 'Chưa thể tạo Slide lúc này. Vui lòng thử lại sau.');
        }
    }

    private function persistResult(LearningObjectGenerationRun $run, array $generated): void
    {
        $payload = $generated['slides'] ?? null;
        if (!is_array($payload) || empty($payload['slides']) || !is_array($payload['slides'])) throw new \RuntimeException('AI service returned an incomplete slide result.');
        DB::transaction(function () use ($run, $payload) {
            $lockedRun = LearningObjectGenerationRun::lockForUpdate()->findOrFail($run->id);
            if ($lockedRun->status !== 'generating') return;
            $parameters = $lockedRun->generation_params;
            $object = $lockedRun->learningObject()->lockForUpdate()->firstOrFail();
            $object->update(['title' => $parameters['requested_title'] ?: $payload['title'], 'description' => $parameters['requested_description'] ?: ($payload['description'] ?? null)]);
            $object->versions()->create([
                'version_number' => 1, 'content_payload' => ['slides' => array_values($payload['slides'])],
                'generation_params' => collect($parameters)->except(['requested_title', 'requested_description'])->all(), 'created_by' => $object->created_by,
            ]);
            $lockedRun->update(['status' => 'completed', 'finished_at' => now()]);
        });
    }

    private function markFailed(LearningObjectGenerationRun $run, string $code, string $message): void
    {
        $run->update(['status' => 'failed', 'error_code' => $code, 'error_message' => $message, 'finished_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = LearningObjectGenerationRun::find($this->runId);
        if ($run && in_array($run->status, ['queued', 'generating'], true)) $this->markFailed($run, 'GENERATION_TIMEOUT', 'Quá trình tạo Slide mất quá nhiều thời gian. Vui lòng thử lại.');
    }
}
