<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentVectorReference;
use App\Services\RagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessTeachingDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $runId)
    {
        // Keep slow extraction/embedding work away from interactive generation jobs.
        $this->onQueue('documents');
    }

    public function handle(RagService $rag): void
    {
        $run = DocumentProcessingRun::with('document')->findOrFail($this->runId);
        $document = $run->document;

        if ($run->status === 'processed' || !$document) {
            return;
        }

        // A retried queue message must not overwrite the outcome of a newer manual retry.
        if ($document->latest_processing_run_id !== $run->id) {
            return;
        }

        $run->update(['status' => 'processing', 'stage' => 'extracting', 'started_at' => now(), 'error_detail' => null]);
        $document->update(['processing_status' => 'processing', 'processing_error' => null]);

        $maxPages = $run->pipeline_config['max_pages'] ?? null;
        $payload = $rag->processDocument($document, $run->id, $maxPages ? (int) $maxPages : null);
        try {
            $this->persistResult($run, $payload);
        } catch (Throwable $exception) {
            // FastAPI has already upserted Chroma vectors. Compensate before Laravel retries so no orphaned vectors leak.
            try {
                $rag->deleteDocumentVectors($document);
            } catch (Throwable) {
                // The original persistence failure is more actionable; failed() records a retryable state for the Lecturer.
            }
            throw $exception;
        }
    }

    private function persistResult(DocumentProcessingRun $run, array $payload): void
    {
        $extraction = $payload['extraction'] ?? null;
        $chunks = $payload['chunks'] ?? null;
        if (!is_array($extraction) || !is_array($chunks) || $chunks === []) {
            throw new \RuntimeException('AI service returned an incomplete processing result.');
        }

        DB::transaction(function () use ($run, $extraction, $chunks, $payload) {
            $run->refresh();
            $document = $run->document()->lockForUpdate()->firstOrFail();
            if ($document->latest_processing_run_id !== $run->id) {
                return;
            }

            $run->update(['stage' => 'persisting']);
            DocumentExtraction::updateOrCreate(
                ['document_processing_run_id' => $run->id],
                [
                    'normalized_text' => (string) ($extraction['normalized_text'] ?? ''),
                    'character_count' => (int) ($extraction['character_count'] ?? 0),
                    'page_count' => isset($extraction['page_count']) ? (int) $extraction['page_count'] : null,
                    'metadata' => $extraction['metadata'] ?? [],
                ]
            );

            foreach ($chunks as $chunk) {
                $storedChunk = DocumentChunk::create([
                    'document_processing_run_id' => $run->id,
                    'chunk_index' => (int) $chunk['chunk_index'],
                    'content' => (string) $chunk['content'],
                    'source_locator' => $chunk['source_locator'] ?? null,
                    'token_estimate' => isset($chunk['token_estimate']) ? (int) $chunk['token_estimate'] : null,
                    'content_hash' => (string) $chunk['content_hash'],
                ]);
                DocumentVectorReference::create([
                    'document_chunk_id' => $storedChunk->id,
                    'provider' => 'chromadb',
                    'embedding_model' => (string) ($payload['embedding_model'] ?? config('rag.embedding_model')),
                    'collection' => (string) ($payload['vector_store']['collection'] ?? 'flts_document_chunks'),
                    'vector_id' => (string) $chunk['vector_id'],
                    'dimensions' => (int) ($chunk['dimensions'] ?? 768),
                ]);
            }

            $run->update(['status' => 'processed', 'stage' => 'completed', 'finished_at' => now()]);
            $document->update([
                'processing_status' => 'processed', 'processing_error' => null,
                'processed_at' => now(),
            ]);
        });
    }

    public function failed(Throwable $exception): void
    {
        $run = DocumentProcessingRun::with('document')->find($this->runId);
        if (!$run) {
            return;
        }

        $run->update([
            'status' => 'failed', 'stage' => 'failed', 'finished_at' => now(),
            // Queue/provider exceptions remain in logs; the persisted run must stay safe to return to a Lecturer.
            'error_detail' => ['code' => 'PROCESSING_FAILED', 'message' => 'Processing could not be completed.', 'retryable' => true],
        ]);
        if ($run->document && $run->document->latest_processing_run_id === $run->id) {
            $run->document->update([
                'processing_status' => 'failed',
                'processing_error' => 'Processing failed. You can retry this document.',
            ]);
        }
    }
}
