<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingDocument extends Model
{
    protected $fillable = [
        'course_id', 'uploaded_by', 'original_name', 'stored_path', 'mime_type',
        'extension', 'size_bytes', 'processing_status', 'processing_error',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
