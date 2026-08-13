<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSubjectGroup;
use App\Models\School;

/**
 * Per-exam average score / pass-rate, computed from ExamMark rows rolled up
 * to their ExamSubjectGroup (one subject, possibly several graded
 * components) against that group's own passing_marks — no GradeBand lookup
 * needed, "pass" here just means "met the subject's own passing threshold."
 *
 * A lightweight, bespoke aggregate across every student in the exam (not
 * per-student), so this deliberately does NOT go through
 * SubjectResultService — that would be one query per student per subject.
 * Grain is per (subject group, student), matching what a report card
 * actually shows — not per component, or a multi-component subject would
 * silently count several times toward one exam's average.
 */
class AcademicReportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentExamPerformance(School $school, int $limit = 6): array
    {
        $exams = Exam::query()
            ->where('is_published', true)
            ->with(['examSubjectGroups.components.marks' => fn ($q) => $q->where('is_absent', false)->whereNotNull('marks_obtained')])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $exams->map(function (Exam $exam) {
            $subjectResults = $exam->examSubjectGroups->flatMap(function (ExamSubjectGroup $group) {
                $maxMarksTotal = (float) $group->components->sum('max_marks');

                return $group->components->flatMap->marks->groupBy('student_id')->map(function ($marks) use ($group, $maxMarksTotal) {
                    $obtained = (float) $marks->sum('marks_obtained');

                    return [
                        'percentage' => $maxMarksTotal > 0 ? ($obtained / $maxMarksTotal) * 100 : null,
                        'is_pass' => $group->passing_marks !== null ? $obtained >= $group->passing_marks : null,
                    ];
                })->values();
            });

            $percentages = $subjectResults->pluck('percentage')->filter(fn ($value) => $value !== null);
            $passableResults = $subjectResults->filter(fn (array $r) => $r['is_pass'] !== null);
            $passCount = $passableResults->filter(fn (array $r) => $r['is_pass'])->count();

            return [
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'entries_count' => $subjectResults->count(),
                'average_percentage' => $percentages->isNotEmpty() ? round($percentages->avg(), 2) : null,
                'pass_rate' => $passableResults->isNotEmpty() ? round(($passCount / $passableResults->count()) * 100, 2) : null,
            ];
        })->reverse()->values()->all();
    }
}
