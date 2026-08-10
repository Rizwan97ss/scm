<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Combines every published exam within a term into one weighted result —
 * the "term report card" a school actually hands out, as opposed to
 * ExamService::reportCard()'s single-exam breakdown.
 */
class TermResultService
{
    public function __construct(private readonly ExamService $exams) {}

    public function termResult(Term $term, Student $student): array
    {
        $examBreakdown = $this->examBreakdownFor($term, $student);

        $weightedPercentage = $this->weightedAverage($examBreakdown, 'percentage');
        $weightedGpa = $this->weightedAverage($examBreakdown, 'gpa');

        $band = null;
        if ($weightedPercentage !== null) {
            $scale = GradingScale::query()->where('is_default', true)->with('gradeBands')->first();
            $band = $scale?->resolveBand($weightedPercentage);
        }

        return [
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
            'term' => ['id' => $term->id, 'name' => $term->name],
            'exams' => $examBreakdown->values(),
            'weighted_percentage' => $weightedPercentage,
            'weighted_gpa' => $weightedGpa,
            'grade_label' => $band?->grade_label,
            'rank' => $this->rank($term, $student),
        ];
    }

    /**
     * @return Collection<int, array{exam: array, weight: float, percentage: ?float, gpa: ?float}>
     */
    private function examBreakdownFor(Term $term, Student $student): Collection
    {
        $exams = Exam::query()->where('term_id', $term->id)->where('is_published', true)->get();

        return $exams->map(function (Exam $exam) use ($student) {
            $report = $this->exams->reportCard($exam, $student);

            return [
                'exam' => ['id' => $exam->id, 'name' => $exam->name],
                'weight' => $exam->weight,
                'percentage' => $report['overall_percentage'],
                'gpa' => $report['overall_gpa'],
            ];
        })->filter(fn ($row) => $row['percentage'] !== null)->values();
    }

    private function weightedAverage(Collection $breakdown, string $key): ?float
    {
        $withValue = $breakdown->filter(fn ($row) => $row[$key] !== null);
        $totalWeight = $withValue->sum('weight');

        if ($totalWeight <= 0) {
            return null;
        }

        $weightedSum = $withValue->sum(fn ($row) => $row[$key] * $row['weight']);

        return round($weightedSum / $totalWeight, 2);
    }

    /**
     * Ranked against every other student currently in the same section who
     * has at least one graded published exam in this term. A student's
     * section membership is read at query time (current_section_id), so a
     * mid-term transfer is ranked against their section as of now, not as of
     * the exam date — a reasonable simplification given sections are the
     * unit rank is meaningful within.
     */
    private function rank(Term $term, Student $student): ?array
    {
        if (! $student->current_section_id) {
            return null;
        }

        $peers = Student::query()->where('current_section_id', $student->current_section_id)->get();

        $scored = $peers
            ->map(fn (Student $peer) => ['student_id' => $peer->id, 'percentage' => $this->weightedAverage($this->examBreakdownFor($term, $peer), 'percentage')])
            ->filter(fn ($row) => $row['percentage'] !== null)
            ->sortByDesc('percentage')
            ->values();

        $position = $scored->search(fn ($row) => $row['student_id'] === $student->id);

        if ($position === false) {
            return null;
        }

        return ['position' => $position + 1, 'out_of' => $scored->count()];
    }
}
