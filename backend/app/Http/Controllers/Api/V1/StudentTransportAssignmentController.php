<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\StoreStudentTransportAssignmentRequest;
use App\Http\Resources\StudentTransportAssignmentResource;
use App\Models\StudentTransportAssignment;
use App\Services\TransportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StudentTransportAssignmentController extends Controller
{
    private const WITH = ['student', 'route', 'routeStop', 'vehicle'];

    public function __construct(private readonly TransportService $transport) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StudentTransportAssignment::class);

        $paginator = QueryBuilder::for(StudentTransportAssignment::query()->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('student_id'), AllowedFilter::exact('route_id'), AllowedFilter::exact('is_active'))
            ->allowedSorts('effective_from', 'created_at')
            ->defaultSort('-effective_from')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(StudentTransportAssignmentResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreStudentTransportAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', StudentTransportAssignment::class);

        $assignment = $this->transport->assign([
            ...$request->validated(),
        ]);

        return ApiResponse::created(new StudentTransportAssignmentResource($assignment->load(self::WITH)));
    }
}
