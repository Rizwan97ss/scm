<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'subject_id', 'type', 'text', 'default_marks', 'explanation', 'created_by'])]
class Question extends Model
{
    use BelongsToSchool, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'default_marks' => 'float',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sequence');
    }

    public function correctOption(): ?QuestionOption
    {
        return $this->options->first(fn (QuestionOption $option) => $option->is_correct);
    }
}
