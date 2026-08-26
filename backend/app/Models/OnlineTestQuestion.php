<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'exam_subject_id', 'question_id', 'marks', 'sequence'])]
class OnlineTestQuestion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'marks' => 'float',
        ];
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function effectiveMarks(): float
    {
        return $this->marks ?? $this->question->default_marks;
    }
}
