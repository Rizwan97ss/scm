<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MarkStaffAttendanceRequest;
use App\Http\Requests\Attendance\UpdateStaffAttendanceRequest;
use App\Http\Resources\StaffAttendanceResource;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StaffAttendanceController extends Controller
{
    private const WITH = ['user', 'markedBy'];

    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * Anyone can hit this endpoint (StaffAttendancePolicy::viewAny is
     * permissive) but only sees their own records unless they hold
     * staff-attendance.view — see StaffAttendancePolicy's class doc.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StaffAttendance::class);

        $query = StaffAttendance::query()->with(self::WITH);

        if (! $request->user()->can('staff-attendance.view')) {
            $query->where('user_id', $request->user()->id);
        }

        $paginator = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::exact('user_id'),
                // See StudentAttendanceController::index() for why this can't be exact().
                AllowedFilter::callback('date', fn ($q, $value) => $q->whereDate('date', $value)),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('date', 'created_at')
            ->defaultSort('-date')
            ->paginate($request->integer('per_page', 30))
            ->appends($request->query());

        return ApiResponse::success(StaffAttendanceResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(MarkStaffAttendanceRequest $request): JsonResponse
    {
        $this->authorize('mark', StaffAttendance::class);

        $records = $this->attendance->markStaffBulk(
            $request->input('entries'),
            Carbon::parse($request->string('date')->toString()),
            $request->user(),
        );

        return ApiResponse::success(
            StaffAttendanceResource::collection($records->load(self::WITH)),
            $records->count().' attendance record(s) saved.'
        );
    }

    public function checkIn(Request $request): JsonResponse
    {
        $this->authorize('selfCheckInOut', StaffAttendance::class);

        $record = $this->attendance->checkIn($request->user());

        return ApiResponse::success(new StaffAttendanceResource($record->load(self::WITH)));
    }

    public function checkOut(Request $request): JsonResponse
    {
        $this->authorize('selfCheckInOut', StaffAttendance::class);

        try {
            $record = $this->attendance->checkOut($request->user());
        } catch (ModelNotFoundException) {
            return ApiResponse::error('You have not checked in today.', 422);
        }

        return ApiResponse::success(new StaffAttendanceResource($record->load(self::WITH)));
    }

    public function update(UpdateStaffAttendanceRequest $request, StaffAttendance $staffAttendance): JsonResponse
    {
        $this->authorize('update', $staffAttendance);

        $updated = $this->attendance->correctStaffAttendance(
            $staffAttendance,
            $request->string('status')->toString(),
            $request->string('remarks')->toString() ?: null,
            $request->user(),
        );

        return ApiResponse::success(new StaffAttendanceResource($updated->load(self::WITH)));
    }

    public function summary(Request $request): JsonResponse
    {
        $targetUserId = $request->integer('user_id') ?: $request->user()->id;

        if ($targetUserId !== $request->user()->id && ! $request->user()->can('staff-attendance.view')) {
            abort(403);
        }

        $staff = User::query()->findOrFail($targetUserId);

        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now();

        return ApiResponse::success($this->attendance->staffSummary($staff, $from, $to));
    }
}
