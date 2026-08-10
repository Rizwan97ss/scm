<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\School;

/**
 * Per-exam average score / pass-rate, computed from ExamMark against its own
 * ExamSubject.passing_marks — no GradeBand lookup needed, "pass" here just
 * means "met the subject's own passing threshold."
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
            ->with(['examSubjects.marks' => fn ($q) => $q->where('is_absent', false)->whereNotNull('marks_obtained')])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $exams->map(function (Exam $exam) {
            $marks = $exam->examSubjects->flatMap->marks;
            $percentages = $exam->examSubjects->flatMap(function ($subject) {
                return $subject->marks->map(fn ($mark) => $subject->max_marks > 0 ? ($mark->marks_obtained / $subject->max_marks) * 100 : null);
            })->filter(fn ($value) => $value !== null);

            $passCount = $exam->examSubjects->sum(fn ($subject) => $subject->marks->where('marks_obtained', '>=', $subject->passing_marks)->count());

            return [
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'entries_count' => $marks->count(),
                'average_percentage' => $percentages->isNotEmpty() ? round($percentages->avg(), 2) : null,
                'pass_rate' => $marks->count() > 0 ? round(($passCount / $marks->count()) * 100, 2) : null,
            ];
        })->reverse()->values()->all();
    }
}
