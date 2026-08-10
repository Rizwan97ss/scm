<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Shared index/show/destroy for straightforward school-scoped CRUD resources
 * (BelongsToSchool already restricts each query to the caller's school).
 * Subclasses implement store()/update() individually with their own Form
 * Request so validation stays specific per entity; index filtering/sorting
 * is declared via $allowedFilters/$allowedSorts/$with.
 */
abstract class CrudController extends Controller
{
    protected array $allowedFilters = [];

    protected array $allowedSorts = ['created_at'];

    protected string $defaultSort = '-created_at';

    protected array $with = [];

    abstract protected function modelClass(): string;

    abstract protected function resourceClass(): string;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', $this->modelClass());

        $paginator = QueryBuilder::for($this->modelClass())
            ->allowedFilters(...$this->allowedFilters)
            ->allowedSorts(...$this->allowedSorts)
            ->defaultSort($this->defaultSort)
            ->with($this->with)
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        $resourceClass = $this->resourceClass();

        return ApiResponse::success($resourceClass::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    public function show(int $id): JsonResponse
    {
        $model = $this->findModel($id);

        $this->authorize('view', $model);

        $resourceClass = $this->resourceClass();

        return ApiResponse::success(new $resourceClass($model));
    }

    public function destroy(int $id): JsonResponse
    {
        $model = $this->findModel($id);

        $this->authorize('delete', $model);

        $model->delete();

        return ApiResponse::noContent();
    }

    protected function findModel(int $id): Model
    {
        $query = $this->modelClass()::query();

        if (! empty($this->with)) {
            $query->with($this->with);
        }

        return $query->findOrFail($id);
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
