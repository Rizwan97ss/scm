<?php

namespace App\Models;

use App\Enums\HomeworkSubmissionStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['school_id', 'homework_id', 'student_id', 'status', 'content', 'submitted_at', 'score', 'feedback', 'graded_at', 'graded_by'])]
class HomeworkSubmission extends Model implements HasMedia
{
    use BelongsToSchool, HasFactory, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'status' => HomeworkSubmissionStatus::class,
            'submitted_at' => 'datetime',
            'score' => 'float',
            'graded_at' => 'datetime',
        ];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }
}
