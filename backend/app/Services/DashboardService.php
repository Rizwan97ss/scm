<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\BookIssue;
use App\Models\ClassSubjectTeacher;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;

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
    public function __construct(private readonly AttendanceService $attendance) {}

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
        $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
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
            'is_class_teacher_of' => Section::query()->where('class_teacher_id', $user->id)->pluck('name', 'id'),
            'todays_attendance_marked_count' => $todaysAttendance->count(),
            'pending_homework_grading_count' => HomeworkSubmission::query()
                ->where('status', 'submitted')
                ->whereHas('homework', fn ($q) => $q->where('teacher_id', $user->id))
                ->count(),
        ];
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
        ];
    }

    private function parentSummary(User $user): array
    {
        $guardian = Guardian::query()->where('user_id', $user->id)->first();
        $childIds = $guardian?->students()->pluck('students.id') ?? collect();

        return [
            'role_context' => 'parent',
            'children_count' => $childIds->count(),
            'children_pending_fees_total' => $childIds->isNotEmpty()
                ? round(Invoice::query()->whereIn('student_id', $childIds)->get()->sum(fn (Invoice $invoice) => $invoice->balance), 2)
                : 0,
        ];
    }
}
