<?php

namespace App\Services;

use App\Enums\EnrollmentAction;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollmentHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every status/roster transition a student goes through (admission, promotion,
 * transfer, withdrawal, graduation, reactivation) is applied here as a single
 * transaction that updates the student's current record AND appends an
 * immutable StudentEnrollmentHistory row, so the roster and the audit trail
 * can never drift apart.
 */
class StudentEnrollmentService
{
    public function recordAdmission(Student $student, User $performedBy, ?Carbon $effectiveDate = null): Student
    {
        return $this->applyTransition(
            student: $student,
            action: EnrollmentAction::Admission,
            performedBy: $performedBy,
            toGradeLevelId: $student->current_grade_level_id,
            toSectionId: $student->current_section_id,
            effectiveDate: $effectiveDate ?? $student->admission_date,
        );
    }

    public function promote(Student $student, GradeLevel $toGradeLevel, Section $toSection, AcademicYear $toAcademicYear, User $performedBy, ?string $reason = null, ?Carbon $effectiveDate = null): Student
    {
        return DB::transaction(function () use ($student, $toGradeLevel, $toSection, $toAcademicYear, $performedBy, $reason, $effectiveDate) {
            $updated = $this->applyTransition(
                student: $student,
                action: EnrollmentAction::Promotion,
                performedBy: $performedBy,
                toGradeLevelId: $toGradeLevel->id,
                toSectionId: $toSection->id,
                reason: $reason,
                effectiveDate: $effectiveDate,
            );

            $updated->forceFill(['academic_year_id' => $toAcademicYear->id])->save();

            return $updated;
        });
    }

    public function transferOut(Student $student, User $performedBy, ?string $reason = null, ?Carbon $effectiveDate = null): Student
    {
        return $this->applyTransition(
            student: $student,
            action: EnrollmentAction::TransferOut,
            performedBy: $performedBy,
            toGradeLevelId: null,
            toSectionId: null,
            reason: $reason,
            effectiveDate: $effectiveDate,
        );
    }

    public function withdraw(Student $student, User $performedBy, ?string $reason = null, ?Carbon $effectiveDate = null): Student
    {
        return $this->applyTransition(
            student: $student,
            action: EnrollmentAction::Withdrawal,
            performedBy: $performedBy,
            toGradeLevelId: null,
            toSectionId: null,
            reason: $reason,
            effectiveDate: $effectiveDate,
        );
    }

    public function graduate(Student $student, User $performedBy, ?string $reason = null, ?Carbon $effectiveDate = null): Student
    {
        return $this->applyTransition(
            student: $student,
            action: EnrollmentAction::Graduation,
            performedBy: $performedBy,
            toGradeLevelId: null,
            toSectionId: null,
            reason: $reason,
            effectiveDate: $effectiveDate,
        );
    }

    public function reactivate(Student $student, GradeLevel $toGradeLevel, Section $toSection, User $performedBy, ?string $reason = null, ?Carbon $effectiveDate = null): Student
    {
        return $this->applyTransition(
            student: $student,
            action: EnrollmentAction::Reactivation,
            performedBy: $performedBy,
            toGradeLevelId: $toGradeLevel->id,
            toSectionId: $toSection->id,
            reason: $reason,
            effectiveDate: $effectiveDate,
        );
    }

    private function applyTransition(
        Student $student,
        EnrollmentAction $action,
        User $performedBy,
        ?int $toGradeLevelId,
        ?int $toSectionId,
        ?string $reason = null,
        ?Carbon $effectiveDate = null,
    ): Student {
        return DB::transaction(function () use ($student, $action, $performedBy, $toGradeLevelId, $toSectionId, $reason, $effectiveDate) {
            $fromGradeLevelId = $student->current_grade_level_id;
            $fromSectionId = $student->current_section_id;

            $student->forceFill([
                'status' => $action->resultingStatus(),
                'current_grade_level_id' => $toGradeLevelId,
                'current_section_id' => $toSectionId,
            ])->save();

            StudentEnrollmentHistory::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $student->academic_year_id,
                'from_grade_level_id' => $fromGradeLevelId,
                'to_grade_level_id' => $toGradeLevelId,
                'from_section_id' => $fromSectionId,
                'to_section_id' => $toSectionId,
                'action' => $action,
                'reason' => $reason,
                'effective_date' => ($effectiveDate ?? now())->toDateString(),
                'performed_by' => $performedBy->id,
            ]);

            return $student->refresh();
        });
    }
}
