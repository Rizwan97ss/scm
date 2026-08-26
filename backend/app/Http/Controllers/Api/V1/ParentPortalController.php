<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExamResource;
use App\Http\Resources\HomeworkResource;
use App\Http\Resources\StudentAttendanceResource;
use App\Http\Resources\StudentRemarkResource;
use App\Http\Resources\StudentResource;
use App\Models\Exam;
use App\Models\Homework;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentRemark;
use App\Models\Term;
use App\Services\AttendanceService;
use App\Services\ExamService;
use App\Services\InvoiceService;
use App\Services\TermResultService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ParentPortalController extends Controller
{
    public function children(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with(['currentGradeLevel', 'currentSection'])
            ->visibleTo($request->user())
            ->get();

        return ApiResponse::success(StudentResource::collection($students));
    }

    public function childProfile(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        return ApiResponse::success(new StudentResource(
            $student->load(['currentGradeLevel', 'currentSection', 'guardians'])
        ));
    }

    public function childAttendance(Request $request, Student $student, AttendanceService $attendance): JsonResponse
    {
        $this->authorize('view', $student);

        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now();

        // whereDate(), not whereBetween('date', [...]) — see AttendanceService's
        // docblock for why a raw string comparison against this column is unsafe.
        $records = StudentAttendance::query()
            ->where('student_id', $student->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereNull('timetable_period_id')
            ->orderByDesc('date')
            ->get();

        return ApiResponse::success([
            'summary' => $attendance->studentSummary($student, $from, $to),
            'records' => StudentAttendanceResource::collection($records),
        ]);
    }

    /**
     * Published exams touching the child's current section — what the
     * parent can pick a report card for. Not gated behind exam-marks.view /
     * exams.view (same rationale as childAttendance(): parent portal routes
     * never depend on the staff permission matrix).
     */
    /**
     * Lists exams touching the child's section that are either fully
     * published OR have at least one individually-declared subject group
     * for that section — a Class Teacher's early per-subject publish (see
     * ExamController::publishGroup()) must make the exam reachable here too,
     * not just once the whole exam is published. reportCard()'s own
     * per-subject status masking is what actually hides any subject not yet
     * declared, once the parent opens it.
     */
    public function childExams(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $exams = Exam::query()
            ->where(function ($q) use ($student) {
                $q->where('is_published', true)
                    ->orWhereHas('examSubjectGroups', fn ($q2) => $q2->where('section_id', $student->current_section_id)->whereNotNull('published_at'))
                    ->orWhereHas('examSubjects', fn ($q2) => $q2->where('section_id', $student->current_section_id)->whereNotNull('exam_date'));
            })
            ->whereHas('examSubjects', fn ($q) => $q->where('section_id', $student->current_section_id))
            ->orderByDesc('published_at')
            ->get();

        return ApiResponse::success(ExamResource::collection($exams));
    }

    /**
     * Homework for the child's current section, newest due date first —
     * each row's submission (if any) comes along via Homework's own
     * scopeVisibleTo()+eager-load pattern being reused here isn't possible
     * (that path is keyed off the Student user, not a Parent viewing a
     * specific child), so it's loaded explicitly for this one student.
     */
    public function childHomework(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $homework = Homework::query()
            ->where('section_id', $student->current_section_id)
            ->with(['section', 'subject', 'teacher', 'submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderByDesc('due_date')
            ->get();

        return ApiResponse::success(HomeworkResource::collection($homework));
    }

    public function childRemarks(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $remarks = StudentRemark::query()
            ->where('student_id', $student->id)
            ->where('visible_to_guardian', true)
            ->with('author')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(StudentRemarkResource::collection($remarks));
    }

    /**
     * Same statement shape InvoiceController::statement() gives the Student
     * their own "Fees" tab — reused as-is here so both surfaces render
     * identically. authorize('view', $student) is the only gate; a Parent
     * doesn't need invoices.view (a staff permission) to see their own
     * child's fees, same rationale as childAttendance()/childHomework().
     */
    public function childInvoices(Request $request, Student $student, InvoiceService $invoices): JsonResponse
    {
        $this->authorize('view', $student);

        return ApiResponse::success($invoices->statementData($student));
    }

    /**
     * No longer requires the whole Exam to be published — reportCard()
     * itself masks any subject not yet individually declared for a
     * Student/Parent viewer, so this stays reachable the moment even one
     * subject has been published, matching ExamController::reportCard()'s
     * identical relaxation (the two surfaces must never disagree).
     */
    public function childReportCard(Request $request, Student $student, ExamService $exams): JsonResponse
    {
        $this->authorize('view', $student);

        $exam = Exam::query()->findOrFail($request->integer('exam_id'));

        return ApiResponse::success($exams->reportCard($exam, $student, $request->user()));
    }

    public function childReportCardPdf(Request $request, Student $student, ExamService $exams): Response
    {
        $this->authorize('view', $student);

        $exam = Exam::query()->findOrFail($request->integer('exam_id'));
        $data = $exams->reportCard($exam, $student, $request->user());

        $pdf = Pdf::loadView('pdf.report-card', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        return $pdf->download(str($data['student']['full_name'].'-'.$exam->name)->slug().'-report-card.pdf');
    }

    public function childTermResult(Request $request, Student $student, TermResultService $termResults): JsonResponse
    {
        $this->authorize('view', $student);

        $term = Term::query()->findOrFail($request->integer('term_id'));

        return ApiResponse::success($termResults->termResult($term, $student));
    }

    public function childTermResultPdf(Request $request, Student $student, TermResultService $termResults): Response
    {
        $this->authorize('view', $student);

        $term = Term::query()->findOrFail($request->integer('term_id'));
        $data = $termResults->termResult($term, $student);

        $pdf = Pdf::loadView('pdf.term-result', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        return $pdf->download(str($data['student']['full_name'].'-'.$data['term']['name'])->slug().'-term-result.pdf');
    }
}
