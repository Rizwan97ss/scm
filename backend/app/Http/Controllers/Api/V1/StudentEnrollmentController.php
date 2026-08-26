<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\BulkPromoteStudentsRequest;
use App\Http\Requests\Student\GraduateStudentRequest;
use App\Http\Requests\Student\PromoteStudentRequest;
use App\Http\Requests\Student\ReactivateStudentRequest;
use App\Http\Requests\Student\TransferStudentRequest;
use App\Http\Requests\Student\WithdrawStudentRequest;
use App\Http\Resources\StudentEnrollmentHistoryResource;
use App\Http\Resources\StudentResource;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentEnrollmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentEnrollmentController extends Controller
{
    public function __construct(private readonly StudentEnrollmentService $enrollment) {}

    public function history(Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $history = $student->enrollmentHistories()
            ->with(['fromGradeLevel', 'toGradeLevel', 'fromSection', 'toSection', 'performedBy'])
            ->get();

        return ApiResponse::success(StudentEnrollmentHistoryResource::collection($history));
    }

    public function promote(PromoteStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('manageEnrollment', $student);

        $updated = $this->enrollment->promote(
            $student,
            GradeLevel::query()->findOrFail($request->integer('to_grade_level_id')),
            Section::query()->findOrFail($request->integer('to_section_id')),
            AcademicYear::query()->findOrFail($request->integer('to_academic_year_id')),
            $request->user(),
            $request->string('reason')->toString() ?: null,
            $request->filled('effective_date') ? Carbon::parse($request->string('effective_date')) : null,
        );

        return ApiResponse::success(new StudentResource($updated->load(['currentGradeLevel', 'currentSection'])), 'Student promoted.');
    }

    public function transfer(TransferStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('manageEnrollment', $student);

        $updated = $this->enrollment->transferOut(
            $student,
            $request->user(),
            $request->string('reason')->toString() ?: null,
            $request->filled('effective_date') ? Carbon::parse($request->string('effective_date')) : null,
        );

        return ApiResponse::success(new StudentResource($updated), 'Student marked as transferred out.');
    }

    public function withdraw(WithdrawStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('manageEnrollment', $student);

        $updated = $this->enrollment->withdraw(
            $student,
            $request->user(),
            $request->string('reason')->toString() ?: null,
            $request->filled('effective_date') ? Carbon::parse($request->string('effective_date')) : null,
        );

        return ApiResponse::success(new StudentResource($updated), 'Student withdrawn.');
    }

    public function graduate(GraduateStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('manageEnrollment', $student);

        $updated = $this->enrollment->graduate(
            $student,
            $request->user(),
            $request->string('reason')->toString() ?: null,
            $request->filled('effective_date') ? Carbon::parse($request->string('effective_date')) : null,
        );

        return ApiResponse::success(new StudentResource($updated), 'Student graduated.');
    }

    public function reactivate(ReactivateStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('manageEnrollment', $student);

        $updated = $this->enrollment->reactivate(
            $student,
            GradeLevel::query()->findOrFail($request->integer('to_grade_level_id')),
            Section::query()->findOrFail($request->integer('to_section_id')),
            $request->user(),
            $request->string('reason')->toString() ?: null,
            $request->filled('effective_date') ? Carbon::parse($request->string('effective_date')) : null,
        );

        return ApiResponse::success(new StudentResource($updated->load(['currentGradeLevel', 'currentSection'])), 'Student reactivated.');
    }

    public function bulkPromote(BulkPromoteStudentsRequest $request): JsonResponse
    {
        $this->authorize('manageEnrollment', Student::class);

        $gradeLevel = GradeLevel::query()->findOrFail($request->integer('to_grade_level_id'));
        $section = Section::query()->findOrFail($request->integer('to_section_id'));
        $academicYear = AcademicYear::query()->findOrFail($request->integer('to_academic_year_id'));
        $reason = $request->string('reason')->toString() ?: null;
        $effectiveDate = $request->filled('effective_date') ? Carbon::parse($request->string('effective_date')) : null;

        $promoted = DB::transaction(function () use ($request, $gradeLevel, $section, $academicYear, $reason, $effectiveDate) {
            $students = Student::query()->whereIn('id', $request->array('student_ids'))->get();

            foreach ($students as $student) {
                $this->enrollment->promote($student, $gradeLevel, $section, $academicYear, $request->user(), $reason, $effectiveDate);
            }

            return $students->count();
        });

        return ApiResponse::success(['promoted_count' => $promoted], "{$promoted} student(s) promoted.");
    }
}
