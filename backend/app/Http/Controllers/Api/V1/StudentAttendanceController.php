<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MarkStudentAttendanceRequest;
use App\Http\Requests\Attendance\UpdateStudentAttendanceRequest;
use App\Http\Resources\StudentAttendanceResource;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TimetablePeriod;
use App\Services\AttendanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StudentAttendanceController extends Controller
{
    private const WITH = ['student', 'section', 'timetablePeriod', 'markedBy'];

    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StudentAttendance::class);

        $paginator = QueryBuilder::for(StudentAttendance::query()->with(self::WITH)->visibleTo($request->user()))
            ->allowedFilters(
                AllowedFilter::exact('section_id'),
                AllowedFilter::exact('student_id'),
                // Not exact('date'): the `date` column is stored with a "Y-m-d
                // H:i:s" value on SQLite (Eloquent's date cast writes using the
                // connection's uniform date format; only MySQL's native DATE
                // column type truncates the time part on write, SQLite's
                // dynamic typing does not — see AttendanceService's docblock),
                // so a plain exact-string match silently never finds a row.
                AllowedFilter::callback('date', fn ($query, $value) => $query->whereDate('date', $value)),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('timetable_period_id'),
            )
            ->allowedSorts('date', 'created_at')
            ->defaultSort('-date')
            ->paginate($request->integer('per_page', 30))
            ->appends($request->query());

        return ApiResponse::success(StudentAttendanceResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(MarkStudentAttendanceRequest $request): JsonResponse
    {
        $this->authorize('mark', StudentAttendance::class);

        $section = Section::query()->findOrFail($request->integer('section_id'));
        $this->assertSectionMarkable($request, $section);

        $period = $request->filled('timetable_period_id')
            ? TimetablePeriod::query()->findOrFail($request->integer('timetable_period_id'))
            : null;

        $records = $this->attendance->markStudentsBulk(
            $section,
            Carbon::parse($request->string('date')->toString()),
            $period,
            $request->input('entries'),
            $request->user(),
        );

        return ApiResponse::success(
            StudentAttendanceResource::collection($records->load(self::WITH)),
            $records->count().' attendance record(s) saved.'
        );
    }

    public function update(UpdateStudentAttendanceRequest $request, StudentAttendance $studentAttendance): JsonResponse
    {
        $this->authorize('update', $studentAttendance);

        $updated = $this->attendance->correctStudentAttendance(
            $studentAttendance,
            $request->string('status')->toString(),
            $request->string('remarks')->toString() ?: null,
            $request->user(),
        );

        return ApiResponse::success(new StudentAttendanceResource($updated->load(self::WITH)));
    }

    public function summary(Request $request): JsonResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));

        $this->authorize('view', $student);

        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now();

        return ApiResponse::success($this->attendance->studentSummary($student, $from, $to));
    }

    public function sectionSummary(Request $request): JsonResponse
    {
        // Gated on 'mark' (student-attendance.mark), not 'viewAny' —
        // student-attendance.view is also held by Student/Parent, and this
        // endpoint takes an arbitrary section_id with no per-record
        // visibleTo() scoping, so it would otherwise let a Student/Parent
        // pull attendance-percentage data for any section in the school,
        // not just their own/their child's.
        $this->authorize('mark', StudentAttendance::class);

        $section = Section::query()->findOrFail($request->integer('section_id'));
        $this->assertSectionMarkable($request, $section);

        $date = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : now();
        $period = $request->filled('timetable_period_id')
            ? TimetablePeriod::query()->findOrFail($request->integer('timetable_period_id'))
            : null;

        return ApiResponse::success($this->attendance->sectionDailySummary($section, $date, $period));
    }

    /**
     * Row-level guard for anything scoped to a Section rather than a single
     * StudentAttendance record (marking, section summaries): a Teacher/Class
     * Teacher may only act on a section they actually teach or lead. Roles with
     * a blanket student-attendance permission beyond their own section (School
     * Admin, Principal, Management) are unrestricted here — the permission
     * check in the calling action already gates them appropriately.
     */
    private function assertSectionMarkable(Request $request, Section $section): void
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Teacher', 'Class Teacher']) || $user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        $allowed = Section::query()
            ->whereKey($section->id)
            ->where(function ($query) use ($user) {
                $query->where('class_teacher_id', $user->id)
                    ->orWhereHas('classSubjectTeachers', fn ($q) => $q->where('teacher_id', $user->id));
            })
            ->exists();

        abort_unless($allowed, 403, 'You are not assigned to this section.');
    }
}
