<?php

namespace App\Models;

use App\Enums\EnrollmentAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id', 'academic_year_id', 'from_grade_level_id', 'to_grade_level_id',
    'from_section_id', 'to_section_id', 'action', 'reason', 'effective_date', 'performed_by',
])]
class StudentEnrollmentHistory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'action' => EnrollmentAction::class,
            'effective_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function fromGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'from_grade_level_id');
    }

    public function toGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'to_grade_level_id');
    }

    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function toSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
