<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'grading_scale_id', 'min_percentage', 'max_percentage', 'grade_label', 'grade_point', 'remark'])]
class GradeBand extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'min_percentage' => 'float',
            'max_percentage' => 'float',
            'grade_point' => 'float',
        ];
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }
}
