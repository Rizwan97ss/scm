<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HomeworkSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Homework\GradeHomeworkSubmissionRequest;
use App\Http\Requests\Homework\SubmitHomeworkRequest;
use App\Http\Resources\HomeworkSubmissionResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkSubmissionController extends Controller
{
    /**
     * The teacher's roster view for one homework: every student in its
     * section, joined with their submission if one exists — not just a
     * list of submissions, since "who hasn't submitted yet" is exactly what
     * a teacher needs to see here.
     */
    public function index(Request $request, Homework $homework): JsonResponse
    {
        // Gated on 'grade', not 'view' — homework.view is also held by
        // Student/Parent (for their own read-only submission view via
        // HomeworkController::show()), but this roster endpoint returns
        // every OTHER student's submission content and grades for the
        // section, which no Student/Parent should ever see regardless of
        // whose homework it is.
        $this->authorize('grade', $homework);
        $this->assertCanGrade($request, $homework);

        $students = Student::query()
            ->where('current_section_id', $homework->section_id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'admission_number']);

        $submissions = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->with('gradedBy')
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(fn (Student $student) => [
            'student' => ['id' => $student->id, 'full_name' => $student->full_name, 'admission_number' => $student->admission_number],
            'submission' => $submissions->has($student->id) ? new HomeworkSubmissionResource($submissions->get($student->id)) : null,
        ]);

        return ApiResponse::success($rows->values());
    }

    /**
     * The acting student's own submission — resolved from the authenticated
     * user, never a client-supplied student_id, same rationale as
     * OnlineTestController::resolveActingStudent(). Upserts rather than
     * requiring a separate "edit" action: resubmitting before grading just
     * replaces the previous content/attachment.
     */
    public function submit(SubmitHomeworkRequest $request, Homework $homework): JsonResponse
    {
        $student = $this->resolveActingStudent($request);
        abort_unless($student->current_section_id === $homework->section_id, 403, 'This homework is not assigned to your section.');

        $submission = HomeworkSubmission::query()->firstOrNew(['homework_id' => $homework->id, 'student_id' => $student->id]);
        $submission->fill([
            'status' => HomeworkSubmissionStatus::Submitted,
            'content' => $request->input('content'),
            'submitted_at' => now(),
        ]);
        $submission->save();

        if ($request->hasFile('file')) {
            $submission->addMediaFromRequest('file')->toMediaCollection('attachments');
        }

        return ApiResponse::success(new HomeworkSubmissionResource($submission->load('gradedBy')), 'Homework submitted.');
    }

    public function grade(GradeHomeworkSubmissionRequest $request, HomeworkSubmission $submission): JsonResponse
    {
        $homework = $submission->homework;
        $this->authorize('grade', $homework);
        $this->assertCanGrade($request, $homework);

        $submission->update([
            'status' => HomeworkSubmissionStatus::Graded,
            'score' => $request->input('score'),
            'feedback' => $request->input('feedback'),
            'graded_at' => now(),
            'graded_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new HomeworkSubmissionResource($submission->load(['student', 'gradedBy'])));
    }

    private function resolveActingStudent(Request $request): Student
    {
        $student = Student::query()->where('user_id', $request->user()->id)->first();
        abort_unless($student, 403, 'No student profile is linked to your account.');

        return $student;
    }

    /**
     * Same rule as HomeworkController::assertCanManage() — viewing the
     * roster or grading is part of managing this homework, so it requires
     * the same "actually teaches this subject/section" check.
     */
    private function assertCanGrade(Request $request, Homework $homework): void
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Teacher', 'Class Teacher']) || $user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($homework->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }
}
