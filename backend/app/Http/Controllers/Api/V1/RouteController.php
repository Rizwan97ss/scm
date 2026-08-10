<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Transport\StoreRouteRequest;
use App\Http\Requests\Transport\StoreRouteStopRequest;
use App\Http\Requests\Transport\UpdateRouteRequest;
use App\Http\Resources\RouteResource;
use App\Http\Resources\RouteStopResource;
use App\Models\Route;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RouteController extends CrudController
{
    protected array $allowedFilters = ['name', 'is_active'];

    protected array $allowedSorts = ['name', 'created_at'];

    protected string $defaultSort = 'name';

    protected array $with = ['stops'];

    protected function modelClass(): string
    {
        return Route::class;
    }

    protected function resourceClass(): string
    {
        return RouteResource::class;
    }

    public function store(StoreRouteRequest $request): JsonResponse
    {
        $this->authorize('create', Route::class);

        $route = DB::transaction(function () use ($request) {
            $route = Route::query()->create([
                ...$request->safe()->except('stops'),
            ]);

            foreach ($request->input('stops', []) as $index => $stop) {
                $route->stops()->create([
                    'name' => $stop['name'],
                    'sequence' => $stop['sequence'] ?? $index + 1,
                ]);
            }

            return $route;
        });

        return ApiResponse::created(new RouteResource($route->load($this->with)));
    }

    public function update(UpdateRouteRequest $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        $route->update($request->validated());

        return ApiResponse::success(new RouteResource($route->load($this->with)));
    }

    public function storeStop(StoreRouteStopRequest $request, Route $route): JsonResponse
    {
        $this->authorize('update', $route);

        $stop = $route->stops()->create([
            ...$request->validated(),
            'sequence' => $request->validated('sequence') ?? $route->stops()->count() + 1,
        ]);

        return ApiResponse::created(new RouteStopResource($stop));
    }

    public function destroyStop(Route $route, int $stop): JsonResponse
    {
        $this->authorize('update', $route);

        $route->stops()->findOrFail($stop)->delete();

        return ApiResponse::noContent();
    }
}
