<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\StoreVisitorRequest;
use App\Http\Resources\VisitorResource;
use App\Models\Visitor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VisitorController extends Controller
{
    private const WITH = ['loggedBy'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Visitor::class);

        $paginator = QueryBuilder::for(Visitor::query()->with(self::WITH))
            ->allowedFilters(
                'name',
                AllowedFilter::callback('date', fn ($q, $value) => $q->whereDate('check_in_time', $value)),
                AllowedFilter::callback('status', fn ($q, $value) => $value === 'checked_in' ? $q->whereNull('check_out_time') : $q->whereNotNull('check_out_time')),
            )
            ->allowedSorts('check_in_time', 'created_at')
            ->defaultSort('-check_in_time')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(VisitorResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreVisitorRequest $request): JsonResponse
    {
        $this->authorize('create', Visitor::class);

        $visitor = Visitor::query()->create([
            ...$request->validated(),
            'check_in_time' => now(),
            'logged_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new VisitorResource($visitor->load(self::WITH)));
    }

    public function checkOut(int $id): JsonResponse
    {
        $visitor = Visitor::query()->findOrFail($id);

        $this->authorize('update', $visitor);

        abort_if($visitor->check_out_time !== null, 422, 'This visitor has already been checked out.');

        $visitor->update(['check_out_time' => now()]);

        return ApiResponse::success(new VisitorResource($visitor->fresh(self::WITH)));
    }
}
