<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_processing_run_id', 'chunk_index', 'content', 'source_locator', 'token_estimate', 'content_hash',
    ];

    protected $casts = ['chunk_index' => 'integer', 'token_estimate' => 'integer'];

    public function processingRun(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingRun::class, 'document_processing_run_id');
    }

    public function vectorReference(): HasOne
    {
        return $this->hasOne(DocumentVectorReference::class);
    }
}
