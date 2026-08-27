<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A section's exam schedule: date + start/end time per subject, laid out
 * as a simple table. Deliberately separate from ExamController's own
 * exams.edit-gated update() flow — that endpoint can restructure an exam
 * wholesale (add/remove subjects, change max marks, ...), while this one
 * only ever touches exam_date/start_time/end_time, so it can safely be
 * opened up to a Class Teacher for their own section without handing them
 * the rest of exam configuration.
 */
class ExamTimetableController extends Controller
{
    /**
     * Reading a section's timetable isn't sensitive the way marks are — any
     * staff member with exam-timetable.view can look at any section's
     * schedule, or a Student/Parent can look at their own (?student_id=,
     * same idiom as ExamController::reportCard()). Only *editing* it is
     * restricted to Admin/Principal or that section's own class teacher.
     */
    public function show(Request $request, Exam $exam): JsonResponse
    {
        $section = $this->resolveSection($request);

        return ApiResponse::success($this->buildResponse($exam, $section, $request->user()));
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        $section = Section::query()->findOrFail($request->integer('section_id'));
        abort_unless($this->canEditSection($request->user(), $section), 403, 'You are not the class teacher of this section.');

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.exam_subject_id' => [
                'required',
                Rule::exists('exam_subjects', 'id')->where('exam_id', $exam->id)->where('section_id', $section->id),
            ],
            'items.*.exam_date' => ['nullable', 'date'],
            'items.*.start_time' => ['nullable', 'date_format:H:i'],
            'items.*.end_time' => ['nullable', 'date_format:H:i', 'after:items.*.start_time'],
        ]);

        foreach ($data['items'] as $item) {
            ExamSubject::query()->whereKey($item['exam_subject_id'])->update([
                'exam_date' => $item['exam_date'] ?? null,
                // Normalized to H:i:s here rather than left as the input's
                // bare H:i — SQLite's TIME columns are untyped TEXT and
                // echo back exactly what was stored, unlike MySQL's real
                // TIME type, which would silently zero-pad only in
                // production; normalizing on write keeps both consistent.
                'start_time' => isset($item['start_time']) ? $item['start_time'].':00' : null,
                'end_time' => isset($item['end_time']) ? $item['end_time'].':00' : null,
            ]);
        }

        return ApiResponse::success($this->buildResponse($exam, $section, $request->user()), 'Timetable saved.');
    }

    private function buildResponse(Exam $exam, Section $section, User $user): array
    {
        $rows = ExamSubject::query()
            ->where('exam_id', $exam->id)
            ->where('section_id', $section->id)
            ->with(['subject', 'assessmentComponentType'])
            ->orderByRaw('exam_date is null, exam_date')
            ->orderByRaw('start_time is null, start_time')
            ->get();

        return [
            'section' => ['id' => $section->id, 'name' => $section->name],
            'can_edit' => $this->canEditSection($user, $section),
            'rows' => $rows->map(fn (ExamSubject $row) => [
                'exam_subject_id' => $row->id,
                'subject_name' => $row->subject->name,
                'component_name' => $row->assessmentComponentType?->name,
                'exam_date' => $row->exam_date?->toDateString(),
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
            ])->values(),
        ];
    }

    /**
     * ?student_id= resolves the caller's own (or their child's) section via
     * the same StudentPolicy::view visibility rule reportCard()/groupResult()
     * already rely on; otherwise a staff caller must pass ?section_id=
     * explicitly and hold exam-timetable.view.
     */
    private function resolveSection(Request $request): Section
    {
        if ($request->filled('student_id')) {
            $student = Student::query()->findOrFail($request->integer('student_id'));
            $this->authorize('view', $student);

            return Section::query()->findOrFail($student->current_section_id);
        }

        abort_unless($request->user()->can('exam-timetable.view'), 403);

        return Section::query()->findOrFail($request->integer('section_id'));
    }

    private function canEditSection(User $user, Section $section): bool
    {
        if (! $user->can('exam-timetable.edit')) {
            return false;
        }

        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return true;
        }

        return $section->class_teacher_id === $user->id;
    }
}
