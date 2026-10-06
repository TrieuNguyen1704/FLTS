<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = ['name', 'code', 'description', 'lecturer_id'];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_enrollments', 'course_id', 'student_id')->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TeachingDocument::class);
    }

    public function learningObjects(): HasMany
    {
        return $this->hasMany(LearningObject::class);
    }
}
