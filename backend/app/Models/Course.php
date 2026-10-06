<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'lecturer_id',
        'enrollment_code', 'is_enrollment_open',
    ];

    protected function casts(): array
    {
        return [
            'is_enrollment_open' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (empty($course->enrollment_code)) {
                $course->enrollment_code = 'FLTS-' . strtoupper(Str::random(6));
            }
            if (!isset($course->is_enrollment_open)) {
                $course->is_enrollment_open = true;
            }
        });
    }

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
