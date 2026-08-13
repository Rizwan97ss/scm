<?php

namespace App\Services;

use App\Models\ExamMark;
use App\Models\ExamSubjectGroup;
use App\Models\GradingScale;
use App\Models\Student;

/**
 * The single place subject totals/percentage/grade/pass-fail are computed
 * from a group's components — ExamService::reportCard(), the group-result
 * endpoint, and the term-result PDF all call through here, so the student
 * portal, teacher portal, admin portal, and report card can never
 * disagree on the numbers.
 *
 * Computed on read, nothing stored — matches TermResultService's existing
 * house style for derived totals in this codebase. Negative-marking's
 * floor-at-zero rule is already applied upstream, in OnlineExamService's
 * per-attempt scoring, by the time marks_obtained lands here — this class
 * only sums what ExamMark already holds, it doesn't re-apply the floor.
 *
 * Partial-absence rule (confirmed with the user): a component the student
 * missed contributes 0 to the subject total, not a hard stop — the whole
 * subject is only shown absent if every one of its components is.
 */
class SubjectResultService
{
    /**
     * @return array{
     *     group: array{id: int, subject: array{id: int, name: string}, section: array{id: int, name: string}, status: string, passing_marks: ?float},
     *     components: array<int, array{id: int, type: ?string, max_marks: float, marks_obtained: ?float, is_absent: bool}>,
     *     max_marks_total: float,
     *     marks_obtained_total: ?float,
     *     is_absent: bool,
     *     percentage: ?float,
     *     grade_label: ?string,
     *     grade_point: ?float,
     *     remark: ?string,
     *     is_pass: ?bool,
     * }
     */
    public function forGroup(ExamSubjectGroup $group, Student $student, ?GradingScale $defaultScale = null): array
    {
        $group->loadMissing(['components.assessmentComponentType', 'subject', 'section', 'gradingScale.gradeBands']);

        $marksByComponent = ExamMark::query()
            ->whereIn('exam_subject_id', $group->components->pluck('id'))
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('exam_subject_id');

        $components = $group->components->map(function ($component) use ($marksByComponent) {
            $mark = $marksByComponent->get($component->id);

            return [
                'id' => $component->id,
                'type' => $component->assessmentComponentType?->name,
                'max_marks' => (float) $component->max_marks,
                'marks_obtained' => $mark?->marks_obtained,
                'is_absent' => $mark?->is_absent ?? false,
            ];
        });

        $maxMarksTotal = (float) $components->sum('max_marks');
        $hasAnyMark = $marksByComponent->isNotEmpty();
        $allComponentsAbsent = $components->isNotEmpty() && $components->every(fn (array $c) => $c['is_absent']);

        $marksObtainedTotal = match (true) {
            $allComponentsAbsent => null,
            ! $hasAnyMark => null,
            default => (float) $components->sum(fn (array $c) => $c['is_absent'] ? 0 : ($c['marks_obtained'] ?? 0)),
        };

        $percentage = ($marksObtainedTotal !== null && $maxMarksTotal > 0)
            ? round($marksObtainedTotal / $maxMarksTotal * 100, 2)
            : null;

        $scale = $group->gradingScale ?? $defaultScale;
        $band = ($percentage !== null && $scale) ? $scale->resolveBand($percentage) : null;

        $isPass = ($marksObtainedTotal !== null && $group->passing_marks !== null)
            ? $marksObtainedTotal >= $group->passing_marks
            : null;

        return [
            'group' => [
                'id' => $group->id,
                'subject' => ['id' => $group->subject->id, 'name' => $group->subject->name],
                'section' => ['id' => $group->section->id, 'name' => $group->section->name],
                'status' => $group->status($hasAnyMark),
                'passing_marks' => $group->passing_marks,
            ],
            'components' => $components->values()->all(),
            'max_marks_total' => $maxMarksTotal,
            'marks_obtained_total' => $marksObtainedTotal,
            'is_absent' => $allComponentsAbsent,
            'percentage' => $percentage,
            'grade_label' => $band?->grade_label,
            'grade_point' => $band?->grade_point,
            'remark' => $band?->remark,
            'is_pass' => $isPass,
        ];
    }
}
