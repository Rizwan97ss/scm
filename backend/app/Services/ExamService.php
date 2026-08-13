<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\ExamSubjectGroup;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Marking mirrors AttendanceService: an upsert keyed on (exam_subject,
 * student), never a plain create, so resubmitting a marks sheet corrects
 * existing rows instead of duplicating them. exam_subject_id here is a
 * single gradable component (Online MCQ, Written, ...) under a subject's
 * ExamSubjectGroup — see SubjectResultService for how components combine
 * into one subject total.
 */
class ExamService
{
    public function __construct(private readonly SubjectResultService $subjectResults) {}

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

    /** Whole-exam bulk publish — stamps every subject group under it too, so per-group and whole-exam publish never disagree once this runs. */
    public function publish(Exam $exam, User $publishedBy): Exam
    {
        DB::transaction(function () use ($exam, $publishedBy) {
            $exam->update(['is_published' => true, 'published_at' => now()]);
            $exam->examSubjectGroups()->update(['published_at' => now(), 'published_by' => $publishedBy->id]);
        });

        return $exam->refresh();
    }

    public function unpublish(Exam $exam): Exam
    {
        DB::transaction(function () use ($exam) {
            $exam->update(['is_published' => false, 'published_at' => null]);
            $exam->examSubjectGroups()->update(['published_at' => null, 'published_by' => null]);
        });

        return $exam->refresh();
    }

    public function publishGroup(ExamSubjectGroup $group, User $publishedBy): ExamSubjectGroup
    {
        $group->update(['published_at' => now(), 'published_by' => $publishedBy->id]);

        return $group->refresh();
    }

    public function unpublishGroup(ExamSubjectGroup $group): ExamSubjectGroup
    {
        $group->update(['published_at' => null, 'published_by' => null]);

        return $group->refresh();
    }

    /**
     * Built from whatever ExamSubjectGroups actually exist for this exam —
     * not from the student's current section — so a mid-exam section
     * change or promotion doesn't erase already-entered results.
     *
     * For a Student/Parent viewer, any subject not yet declared (exam not
     * published AND that subject's own group not individually published)
     * comes back with only its status — no marks, percentage, or grade —
     * so a Class Teacher's early per-subject publish is what actually
     * controls what a student sees, not just the whole-exam flag. Staff
     * viewers always see every subject regardless of status, matching how
     * ExamMark::scopeVisibleTo() already treats staff today.
     *
     * $viewer is nullable for trusted internal callers only (TermResultService,
     * which pre-filters to already-fully-published exams before calling this,
     * so masking would never trigger anyway) — every controller call site
     * must pass the real acting user.
     */
    public function reportCard(Exam $exam, Student $student, ?User $viewer = null): array
    {
        $groups = ExamSubjectGroup::query()
            ->where('exam_id', $exam->id)
            ->with(['components.assessmentComponentType', 'subject', 'section', 'gradingScale.gradeBands'])
            ->get();

        $defaultScale = GradingScale::query()->where('is_default', true)->with('gradeBands')->first();
        $isStudentOrParent = $viewer?->hasAnyRole(['Student', 'Parent']) ?? false;

        $rows = $groups->map(function (ExamSubjectGroup $group) use ($student, $defaultScale, $exam, $isStudentOrParent) {
            $result = $this->subjectResults->forGroup($group, $student, $defaultScale);

            $isDeclared = $exam->is_published || $group->published_at !== null;

            if ($isStudentOrParent && ! $isDeclared) {
                return [
                    ...$result,
                    'components' => [],
                    'marks_obtained_total' => null,
                    'is_absent' => false,
                    'percentage' => null,
                    'grade_label' => null,
                    'grade_point' => null,
                    'remark' => null,
                    'is_pass' => null,
                ];
            }

            return $result;
        })->values();

        $gradedRows = $rows->filter(fn (array $row) => $row['percentage'] !== null);
        $gpaRows = $gradedRows->filter(fn (array $row) => $row['grade_point'] !== null);

        return [
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
            'exam' => ['id' => $exam->id, 'name' => $exam->name, 'is_published' => $exam->is_published],
            'subjects' => $rows,
            'overall_percentage' => $gradedRows->isNotEmpty() ? round($gradedRows->avg('percentage'), 2) : null,
            'overall_gpa' => $gpaRows->isNotEmpty() ? round($gpaRows->avg('grade_point'), 2) : null,
        ];
    }
}
