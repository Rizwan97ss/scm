<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ReviewLeaveRequestRequest;
use App\Http\Requests\Hr\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LeaveRequestController extends Controller
{
    private const WITH = ['user', 'leaveType', 'reviewedBy'];

    public function __construct(private readonly LeaveService $leave) {}

    /**
     * Anyone can hit this endpoint (LeaveRequestPolicy::viewAny is
     * permissive) but only sees their own requests unless they hold
     * leave.view — same shape as StaffAttendanceController::index().
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $query = LeaveRequest::query()->with(self::WITH);

        if (! $request->user()->can('leave.view')) {
            $query->where('user_id', $request->user()->id);
        }

        $paginator = QueryBuilder::for($query)
            ->allowedFilters(AllowedFilter::exact('user_id'), AllowedFilter::exact('status'), AllowedFilter::exact('leave_type_id'))
            ->allowedSorts('start_date', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(LeaveRequestResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $request = request();
        $query = LeaveRequest::query()->with(self::WITH);

        if (! $request->user()->can('leave.view')) {
            $query->where('user_id', $request->user()->id);
        }

        $leaveRequest = $query->findOrFail($id);

        $this->authorize('view', $leaveRequest);

        return ApiResponse::success(new LeaveRequestResource($leaveRequest));
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $leaveRequest = $this->leave->apply($request->validated(), $request->user());

        return ApiResponse::created(new LeaveRequestResource($leaveRequest->load(self::WITH)));
    }

    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $this->leave->cancel($leaveRequest);

        return ApiResponse::success(new LeaveRequestResource($leaveRequest->fresh(self::WITH)));
    }

    public function review(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('review', $leaveRequest);

        $updated = $this->leave->review($leaveRequest, $request->validated(), $request->user());

        return ApiResponse::success(new LeaveRequestResource($updated->load(self::WITH)));
    }
}
