<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $fillable = ['quiz_id', 'question_index', 'question_text', 'question_type', 'explanation', 'citations'];

    protected function casts(): array { return ['citations' => 'array']; }

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }
    public function options(): HasMany { return $this->hasMany(QuizOption::class)->orderBy('option_index'); }
}
