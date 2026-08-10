<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * All permissions grouped by module, for the role editor's permission matrix UI.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $names = Permission::query()->orderBy('name')->pluck('name');

        $grouped = $names->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($permissions) => $permissions->map(fn (string $name) => [
                'name' => $name,
                'action' => explode('.', $name)[1] ?? $name,
            ])->values());

        return ApiResponse::success($grouped);
    }
}
