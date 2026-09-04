<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_material_id', 'student_id', 'viewed_at', 'completed_at'])]
class CourseMaterialProgress extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
