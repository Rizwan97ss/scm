<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'attempt_id', 'question_id', 'selected_option_id', 'is_correct', 'marks_awarded'])]
class OnlineTestAnswer extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'marks_awarded' => 'float',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OnlineTestAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }
}
