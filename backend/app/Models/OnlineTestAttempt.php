<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'exam_subject_id', 'student_id', 'attempt_number', 'status', 'started_at', 'submitted_at', 'score', 'max_score', 'auto_submit_reason', 'violation_count', 'last_seen_at'])]
class OnlineTestAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'score' => 'float',
            'max_score' => 'float',
        ];
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(OnlineTestAnswer::class, 'attempt_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OnlineTestAttemptEvent::class, 'attempt_id');
    }
}
