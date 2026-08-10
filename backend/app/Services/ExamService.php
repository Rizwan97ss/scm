<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Marking mirrors AttendanceService: an upsert keyed on (exam_subject,
 * student), never a plain create, so resubmitting a marks sheet corrects
 * existing rows instead of duplicating them.
 */
class ExamService
{
    /**
     * @param  array<int, array{student_id: int, marks_obtained?: float|null, is_absent?: bool, remarks?: ?string}>  $entries
     */
    public function markBulk(ExamSubject $examSubject, array $entries, User $enteredBy): EloquentCollection
    {
        return DB::transaction(function () use ($examSubject, $entries, $enteredBy) {
            $records = collect($entries)->map(function (array $entry) use ($examSubject, $enteredBy) {
                $isAbsent = $entry['is_absent'] ?? false;

                $attributes = [
                    'exam_subject_id' => $examSubject->id,
                    'student_id' => $entry['student_id'],
                    'marks_obtained' => $isAbsent ? null : ($entry['marks_obtained'] ?? null),
                    'is_absent' => $isAbsent,
                    'remarks' => $entry['remarks'] ?? null,
                    'entered_by' => $enteredBy->id,
                ];

                $existing = ExamMark::query()
                    ->where('exam_subject_id', $examSubject->id)
                    ->where('student_id', $entry['student_id'])
                    ->first();

                if ($existing) {
                    $existing->update($attributes);

                    return $existing;
                }

                return ExamMark::query()->create($attributes);
            });

            return EloquentCollection::make($records->all());
        });
    }

    public function correctMark(ExamMark $mark, array $data, User $enteredBy): ExamMark
    {
        $mark->update([...$data, 'entered_by' => $enteredBy->id]);

        return $mark->refresh();
    }

    public function publish(Exam $exam): Exam
    {
        $exam->update(['is_published' => true, 'published_at' => now()]);

        return $exam->refresh();
    }

    public function unpublish(Exam $exam): Exam
    {
        $exam->update(['is_published' => false, 'published_at' => null]);

        return $exam->refresh();
    }

    /**
     * Built from whatever ExamMark rows actually exist for this student in
     * this exam — not from the student's current section — so a mid-exam
     * section change or promotion doesn't erase already-entered results.
     */
    public function reportCard(Exam $exam, Student $student): array
    {
        $marks = ExamMark::query()
            ->where('student_id', $student->id)
            ->whereHas('examSubject', fn ($q) => $q->where('exam_id', $exam->id))
            ->with(['examSubject.subject', 'examSubject.gradingScale.gradeBands'])
            ->get();

        $defaultScale = null;

        $rows = $marks->map(function (ExamMark $mark) use (&$defaultScale) {
            $examSubject = $mark->examSubject;
            $percentage = $mark->percentage;

            $scale = $examSubject->gradingScale;
            if (! $scale) {
                $defaultScale ??= GradingScale::query()->where('school_id', $examSubject->school_id)->where('is_default', true)->with('gradeBands')->first();
                $scale = $defaultScale;
            }

            $band = $percentage !== null && $scale ? $scale->resolveBand($percentage) : null;

            return [
                'subject' => ['id' => $examSubject->subject->id, 'name' => $examSubject->subject->name],
                'max_marks' => $examSubject->max_marks,
                'passing_marks' => $examSubject->passing_marks,
                'marks_obtained' => $mark->marks_obtained,
                'is_absent' => $mark->is_absent,
                'percentage' => $percentage,
                'grade_label' => $band?->grade_label,
                'grade_point' => $band?->grade_point,
                'remark' => $band?->remark,
                'remarks' => $mark->remarks,
            ];
        })->values();

        $gradedRows = $rows->filter(fn ($row) => $row['percentage'] !== null);
        $gpaRows = $gradedRows->filter(fn ($row) => $row['grade_point'] !== null);

        return [
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
            'exam' => ['id' => $exam->id, 'name' => $exam->name, 'is_published' => $exam->is_published],
            'subjects' => $rows,
            'overall_percentage' => $gradedRows->isNotEmpty() ? round($gradedRows->avg('percentage'), 2) : null,
            'overall_gpa' => $gpaRows->isNotEmpty() ? round($gpaRows->avg('grade_point'), 2) : null,
        ];
    }
}
