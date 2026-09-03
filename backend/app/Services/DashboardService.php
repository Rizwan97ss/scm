<?php

namespace App\Services;

use App\Enums\PayslipStatus;
use App\Models\Announcement;
use App\Models\BookIssue;
use App\Models\ClassSubjectTeacher;
use App\Models\ExamSubject;
use App\Models\ExamSubjectGroup;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentTransportAssignment;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Role-aware dashboard summary. Phase 0-3 only had identity/academic/student
 * data to report on; each later phase's data slots in here the same way,
 * one optional field at a time — gated by the same permission that module's
 * own pages already require, so a widget never leaks data its role couldn't
 * otherwise see (e.g. fee/library counts on the staff dashboard only appear
 * for a caller who actually holds invoices.view-reports/library.view).
 */
class DashboardService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly FeeReportService $feeReports,
        private readonly OperationsReportService $operationsReports,
        private readonly AcademicReportService $academicReports,
        private readonly SubjectResultService $subjectResults,
    ) {}

    public function summaryFor(User $user): array
    {
        if ($user->hasRole('Student')) {
            return $this->studentSummary($user);
        }

        if ($user->hasRole('Parent')) {
            return $this->parentSummary($user);
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            return $this->teacherSummary($user);
        }

        return $this->staffSummary($user);
    }

    private function staffSummary(User $user): array
    {
        // whereDate(), not where('date', ...) — see AttendanceService's docblock.
        $todayCount = StudentAttendance::query()->whereDate('date', now())->whereNull('timetable_period_id')->count();

        // Computed once and shared by the transport/hostel fields below — OperationsReportService::summary()
        // already gates each section by permission internally, so calling it twice would recompute for nothing.
        $operations = ($user->can('transport.view') || $user->can('hostel.view'))
            ? $this->operationsReports->summary(tenant(), $user)
            : null;

        return [
            'role_context' => 'staff',
            'student_count' => Student::query()->where('status', 'active')->count(),
            'staff_count' => User::query()->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['Student', 'Parent']))->count(),
            'section_count' => Section::query()->count(),
            'todays_attendance_marked_count' => $todayCount,
            'pending_leave_requests_count' => $user->can('leave.manage')
                ? LeaveRequest::query()->where('status', 'pending')->count()
                : null,
            'fee_collected_this_month' => $user->can('invoices.view-reports')
                ? round(Payment::query()->whereDate('paid_at', '>=', now()->startOfMonth())->sum('amount'), 2)
                : null,
            'outstanding_fees_total' => $user->can('invoices.view-reports')
                ? round(Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->get()->sum(fn (Invoice $invoice) => $invoice->balance), 2)
                : null,
            'library_overdue_count' => $user->can('library.view')
                ? BookIssue::query()->where('status', 'issued')->whereDate('due_date', '<', now())->count()
                : null,
            'attendance_trend' => $user->can('student-attendance.view') ? $this->attendanceTrend() : null,
            'fee_trend' => $user->can('invoices.view-reports') ? $this->feeTrend() : null,
            'enrollment_trend' => $this->enrollmentTrend(),
            'grade_distribution' => $this->gradeDistribution(),
            'upcoming_exams' => $user->can('exams.view') ? $this->upcomingExams() : null,
            'recent_announcements' => $this->recentAnnouncements(),
            'pending_leave_requests' => $user->can('leave.manage') ? $this->pendingLeaveRequests() : null,
            'fee_collection_this_month' => $user->can('invoices.view-reports')
                ? $this->feeReports->collectionSummary(tenant(), null, null)
                : null,
            'outstanding_invoices' => $user->can('invoices.view-reports') ? $this->outstandingInvoicesSummary() : null,
            'library_due_soon' => $user->can('library.view') ? $this->libraryDueSoon() : null,
            'transport_summary' => $operations['transport'] ?? null,
            'transport_today_assignments' => $user->can('transport.view') ? $this->transportTodayAssignments() : null,
            'hostel_summary' => $operations['hostel'] ?? null,
            'payroll_summary' => ($user->can('payroll.view') || $user->can('payroll.manage')) ? $this->payrollSummary() : null,
            'visitors_today' => $user->can('front-desk.view') ? $this->visitorsToday() : null,
            'recent_exam_performance' => $user->can('exam-marks.view') ? $this->academicReports->recentExamPerformance(tenant(), 5) : null,
        ];
    }

    /** @return array{total_outstanding: float, overdue_count: int, top: array<int, array{id: int, invoice_number: string, student_name: ?string, balance: float, due_date: ?string}>} */
    private function outstandingInvoicesSummary(): array
    {
        $dues = $this->feeReports->outstandingDues(tenant());

        $top = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->with('student:id,first_name,last_name')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance > 0)
            ->sortByDesc(fn (Invoice $invoice) => $invoice->balance)
            ->take(5)
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'student_name' => $invoice->student?->full_name,
                'balance' => round($invoice->balance, 2),
                'due_date' => $invoice->due_date?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'total_outstanding' => $dues['total_outstanding'],
            'overdue_count' => $dues['overdue_count'],
            'top' => $top,
        ];
    }

    /** @return array<int, array{id: int, book_title: string, borrower_name: ?string, due_date: string}> */
    private function libraryDueSoon(): array
    {
        return BookIssue::query()
            ->where('status', 'issued')
            ->whereDate('due_date', '>=', now())
            ->whereDate('due_date', '<=', now()->addDays(7))
            ->with(['book:id,title', 'student:id,first_name,last_name', 'user:id,first_name,last_name'])
            ->orderBy('due_date')
            ->take(5)
            ->get()
            ->map(fn (BookIssue $issue) => [
                'id' => $issue->id,
                'book_title' => $issue->book?->title ?? '—',
                'borrower_name' => $issue->borrower_name,
                'due_date' => $issue->due_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, student_name: string, route: ?string, vehicle: ?string}> */
    private function transportTodayAssignments(): array
    {
        return StudentTransportAssignment::query()
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', now())
            ->with(['student:id,first_name,last_name', 'route:id,name', 'vehicle:id,registration_number'])
            ->take(5)
            ->get()
            ->map(fn (StudentTransportAssignment $assignment) => [
                'id' => $assignment->id,
                'student_name' => $assignment->student?->full_name ?? '—',
                'route' => $assignment->route?->name,
                'vehicle' => $assignment->vehicle?->registration_number,
            ])
            ->values()
            ->all();
    }

    /** @return array{paid_count: int, pending_count: int, total_net: float} */
    private function payrollSummary(): array
    {
        $payslips = Payslip::query()->where('month', now()->month)->where('year', now()->year)->get();

        return [
            'paid_count' => $payslips->where('status', PayslipStatus::Paid)->count(),
            'pending_count' => $payslips->where('status', PayslipStatus::Generated)->count(),
            'total_net' => round((float) $payslips->sum('net_salary'), 2),
        ];
    }

    /** @return array{total_today: int, checked_in_now: int} */
    private function visitorsToday(): array
    {
        $today = Visitor::query()->whereDate('check_in_time', now())->get();

        return [
            'total_today' => $today->count(),
            'checked_in_now' => $today->whereNull('check_out_time')->count(),
        ];
    }

    /**
     * Daily attendance percentage for the last 14 days — one query for the
     * whole window (not one per day), bucketed in PHP. Same DB-portability
     * reasoning as PlatformMetricsController's monthly signup trend: date
     * grouping in SQL (DATE_FORMAT/strftime) isn't portable across MySQL
     * (prod) and SQLite (tests), and this window is small enough that
     * bucketing in PHP costs nothing.
     */
    private function attendanceTrend(): array
    {
        $from = now()->subDays(13)->startOfDay();

        $records = StudentAttendance::query()
            ->whereDate('date', '>=', $from)
            ->whereNull('timetable_period_id')
            ->get(['date', 'status']);

        $byDate = $records->groupBy(fn (StudentAttendance $record) => $record->date->format('Y-m-d'));

        return collect(range(13, 0))->map(function (int $daysAgo) use ($byDate) {
            $date = now()->subDays($daysAgo);
            $key = $date->format('Y-m-d');
            $dayRecords = $byDate->get($key, collect());
            $total = $dayRecords->count();
            $presentEquivalent = $dayRecords->sum(fn (StudentAttendance $record) => $record->status->presentWeight());

            return [
                'date' => $key,
                'label' => $date->format('M j'),
                'percentage' => $total > 0 ? round(($presentEquivalent / $total) * 100, 1) : null,
            ];
        })->values()->all();
    }

    /** @return array<int, array{month: string, label: string, amount: float}> */
    private function feeTrend(): array
    {
        $from = now()->startOfMonth()->subMonths(5);

        $byMonth = Payment::query()
            ->where('paid_at', '>=', $from)
            ->get(['paid_at', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('Y-m'));

        return collect(range(5, 0))->map(function (int $monthsAgo) use ($byMonth) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $key = $month->format('Y-m');

            return [
                'month' => $key,
                'label' => $month->format('M Y'),
                'amount' => round((float) $byMonth->get($key, collect())->sum('amount'), 2),
            ];
        })->values()->all();
    }

    /** @return array<int, array{month: string, label: string, count: int}> */
    private function enrollmentTrend(): array
    {
        $from = now()->startOfMonth()->subMonths(5);

        $byMonth = Student::query()
            ->where('admission_date', '>=', $from)
            ->get(['admission_date'])
            ->groupBy(fn (Student $student) => $student->admission_date->format('Y-m'));

        return collect(range(5, 0))->map(function (int $monthsAgo) use ($byMonth) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $key = $month->format('Y-m');

            return [
                'month' => $key,
                'label' => $month->format('M Y'),
                'count' => $byMonth->get($key, collect())->count(),
            ];
        })->values()->all();
    }

    /** @return array<int, array{grade_level: string, count: int}> */
    private function gradeDistribution(): array
    {
        $counts = Student::query()
            ->where('status', 'active')
            ->whereNotNull('current_grade_level_id')
            ->selectRaw('current_grade_level_id, count(*) as count')
            ->groupBy('current_grade_level_id')
            ->pluck('count', 'current_grade_level_id');

        return GradeLevel::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('sequence')
            ->get(['id', 'name'])
            ->map(fn (GradeLevel $level) => [
                'grade_level' => $level->name,
                'count' => (int) $counts->get($level->id, 0),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, name: string, date: string}> */
    private function upcomingExams(): array
    {
        return ExamSubject::query()
            ->whereDate('exam_date', '>=', now())
            ->with('exam:id,name')
            ->orderBy('exam_date')
            ->get(['id', 'exam_id', 'exam_date'])
            ->unique('exam_id')
            ->take(5)
            ->map(fn (ExamSubject $subject) => [
                'id' => $subject->exam_id,
                'name' => $subject->exam?->name ?? '—',
                'date' => $subject->exam_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, title: string, sent_at: ?string}> */
    private function recentAnnouncements(): array
    {
        return Announcement::query()
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->take(5)
            ->get(['id', 'title', 'sent_at'])
            ->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'sent_at' => $announcement->sent_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, staff_name: string, leave_type: string, from: string, to: string}> */
    private function pendingLeaveRequests(): array
    {
        return LeaveRequest::query()
            ->where('status', 'pending')
            ->with(['user:id,first_name,last_name', 'leaveType:id,name'])
            ->orderBy('start_date')
            ->take(5)
            ->get(['id', 'user_id', 'leave_type_id', 'start_date', 'end_date'])
            ->map(fn (LeaveRequest $request) => [
                'id' => $request->id,
                'staff_name' => trim(($request->user?->first_name ?? '').' '.($request->user?->last_name ?? '')),
                'leave_type' => $request->leaveType?->name ?? '—',
                'from' => $request->start_date->toDateString(),
                'to' => $request->end_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function teacherSummary(User $user): array
    {
        $classTeacherSections = Section::query()->where('class_teacher_id', $user->id)->get(['id', 'name']);
        $sectionIds = $classTeacherSections->pluck('id')
            ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
            ->unique();

        $todaysAttendance = StudentAttendance::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('date', now())
            ->whereNull('timetable_period_id')
            ->get();

        return [
            'role_context' => 'teacher',
            'assigned_section_count' => $sectionIds->count(),
            'student_count' => Student::query()->whereIn('current_section_id', $sectionIds)->count(),
            'is_class_teacher_of' => $classTeacherSections->pluck('name', 'id'),
            'todays_attendance_marked_count' => $todaysAttendance->count(),
            'pending_homework_grading_count' => HomeworkSubmission::query()
                ->where('status', 'submitted')
                ->whereHas('homework', fn ($q) => $q->where('teacher_id', $user->id))
                ->count(),
            'section_today' => $classTeacherSections->isNotEmpty() ? $this->sectionsTodayAttendance($classTeacherSections) : null,
        ];
    }

    /** @return array<int, array{section_id: int, section_name: string, summary: array}> */
    private function sectionsTodayAttendance(Collection $sections): array
    {
        return $sections->map(fn (Section $section) => [
            'section_id' => $section->id,
            'section_name' => $section->name,
            'summary' => $this->attendance->sectionDailySummary($section, now()),
        ])->values()->all();
    }

    private function studentSummary(User $user): array
    {
        $student = Student::query()->where('user_id', $user->id)->first();

        return [
            'role_context' => 'student',
            'student' => $student ? [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'grade_level' => $student->currentGradeLevel?->name,
                'section' => $student->currentSection?->name,
            ] : null,
            'attendance_this_month' => $student
                ? $this->attendance->studentSummary($student, now()->startOfMonth(), now())
                : null,
            'pending_homework_count' => $student
                ? Homework::query()->where('section_id', $student->current_section_id)
                    ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $student->id))
                    ->count()
                : null,
            'upcoming_exam_count' => $student
                ? ExamSubject::query()->where('section_id', $student->current_section_id)
                    ->whereDate('exam_date', '>=', now())
                    ->count()
                : null,
            'upcoming_exams' => $student && $student->current_section_id
                ? $this->upcomingExamsForSection($student->current_section_id)
                : null,
            'recent_grades' => $student ? $this->recentGrades($student) : null,
        ];
    }

    /** @return array<int, array{id: int, name: string, date: string}> */
    private function upcomingExamsForSection(int $sectionId): array
    {
        return ExamSubject::query()
            ->where('section_id', $sectionId)
            ->whereDate('exam_date', '>=', now())
            ->with('exam:id,name')
            ->orderBy('exam_date')
            ->get(['id', 'exam_id', 'exam_date'])
            ->unique('exam_id')
            ->take(5)
            ->map(fn (ExamSubject $subject) => [
                'id' => $subject->exam_id,
                'name' => $subject->exam?->name ?? '—',
                'date' => $subject->exam_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{exam_name: ?string, subject: ?string, percentage: ?float, grade_label: ?string}> */
    private function recentGrades(Student $student): array
    {
        if (! $student->current_section_id) {
            return [];
        }

        return ExamSubjectGroup::query()
            ->where('section_id', $student->current_section_id)
            ->whereNotNull('published_at')
            ->with(['subject:id,name', 'exam:id,name'])
            ->orderByDesc('published_at')
            ->take(5)
            ->get()
            ->map(function (ExamSubjectGroup $group) use ($student) {
                $result = $this->subjectResults->forGroup($group, $student);

                return [
                    'exam_name' => $group->exam?->name,
                    'subject' => $group->subject?->name,
                    'percentage' => $result['percentage'],
                    'grade_label' => $result['grade_label'],
                ];
            })
            ->values()
            ->all();
    }

    private function parentSummary(User $user): array
    {
        $guardian = Guardian::query()->where('user_id', $user->id)->first();
        $children = $guardian
            ? $guardian->students()->with(['currentGradeLevel', 'currentSection'])->get()
            : collect();

        return [
            'role_context' => 'parent',
            'children_count' => $children->count(),
            'children' => $children->map(fn (Student $child) => [
                'id' => $child->id,
                'name' => $child->full_name,
                'grade_level' => $child->currentGradeLevel?->name,
                'section' => $child->currentSection?->name,
                'attendance_percentage' => $this->attendance->studentSummary($child, now()->startOfMonth(), now())['percentage'],
                'pending_fees' => round(
                    Invoice::query()->where('student_id', $child->id)->whereIn('status', ['issued', 'partially_paid'])->get()
                        ->sum(fn (Invoice $invoice) => $invoice->balance),
                    2
                ),
                'upcoming_exam_count' => $child->current_section_id
                    ? ExamSubject::query()->where('section_id', $child->current_section_id)->whereDate('exam_date', '>=', now())->count()
                    : 0,
            ])->values()->all(),
        ];
    }
}
