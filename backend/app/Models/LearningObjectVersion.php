<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningObjectVersion extends Model
{
    protected $fillable = ['learning_object_id', 'version_number', 'content_payload', 'generation_params', 'created_by'];

    protected function casts(): array
    {
        return ['content_payload' => 'array', 'generation_params' => 'array'];
    }

    public function learningObject(): BelongsTo { return $this->belongsTo(LearningObject::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
