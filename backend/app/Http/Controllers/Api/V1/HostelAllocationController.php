<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\StoreHostelAllocationRequest;
use App\Http\Resources\HostelAllocationResource;
use App\Models\HostelAllocation;
use App\Services\HostelService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class HostelAllocationController extends Controller
{
    private const WITH = ['student', 'hostelRoom.hostel'];

    public function __construct(private readonly HostelService $hostel) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HostelAllocation::class);

        $paginator = QueryBuilder::for(HostelAllocation::query()->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('student_id'), AllowedFilter::exact('hostel_room_id'), AllowedFilter::exact('status'))
            ->allowedSorts('allocated_date', 'created_at')
            ->defaultSort('-allocated_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(HostelAllocationResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreHostelAllocationRequest $request): JsonResponse
    {
        $this->authorize('create', HostelAllocation::class);

        $allocation = $this->hostel->allocate($request->validated());

        return ApiResponse::created(new HostelAllocationResource($allocation->load(self::WITH)));
    }

    public function vacate(int $id): JsonResponse
    {
        $allocation = HostelAllocation::query()->findOrFail($id);

        $this->authorize('update', $allocation);

        $updated = $this->hostel->vacate($allocation);

        return ApiResponse::success(new HostelAllocationResource($updated->load(self::WITH)));
    }
}
