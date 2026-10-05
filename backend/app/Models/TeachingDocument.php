<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingDocument extends Model
{
    protected $fillable = [
        'course_id', 'uploaded_by', 'original_name', 'stored_path', 'mime_type',
        'extension', 'size_bytes', 'processing_status', 'processing_error',
        'latest_processing_run_id', 'processed_at',
    ];

    protected $casts = ['size_bytes' => 'integer', 'processed_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function processingRuns(): HasMany
    {
        return $this->hasMany(DocumentProcessingRun::class);
    }

    public function latestProcessingRun(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingRun::class, 'latest_processing_run_id');
    }
}
