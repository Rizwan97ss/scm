<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformSchoolResource;
use App\Models\School;
use App\Models\User;
use App\Services\AnonymizationService;
use App\Services\SchoolProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Cross-tenant — every other controller in this app scopes to the caller's
 * own school (BelongsToSchool/SchoolScope); this one deliberately doesn't,
 * since listing/managing every tenant IS the point. Authorized via the
 * 'platform.view-tenants' PERMISSION directly (Spatie auto-registers every
 * permission name as a Gate ability — see config/permission.php's
 * register_permission_check_method), not a Policy — there's no single
 * School instance to check "view" against for an index of all of them, and
 * this is a categorically different concern from SchoolPolicy (which
 * governs a School Admin's own view of their one school).
 */
class PlatformSchoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('platform.view-tenants');

        $paginator = QueryBuilder::for(School::query()->with('plan'))
            ->allowedFilters(AllowedFilter::exact('billing_status'), AllowedFilter::exact('plan_id'), 'name')
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(PlatformSchoolResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function show(School $school): JsonResponse
    {
        $this->authorize('platform.view-tenants');

        return ApiResponse::success(new PlatformSchoolResource($school->load('plan')));
    }

    /**
     * Ends a school's tenancy — 'delete' physically drops the tenant
     * database (irreversible, reuses SchoolProvisioningService::teardown(),
     * the same sequence a failed signup's rollback already uses); 'anonymize'
     * scrubs every account's PII in place (AnonymizationService, the same
     * per-model rules the individual-user workflow uses) and deactivates
     * the school, but leaves the database itself intact — financial/
     * academic records stay queryable, matching the individual-anonymize
     * philosophy applied at tenant scope instead of dropping everything.
     */
    public function offboard(Request $request, School $school, SchoolProvisioningService $provisioning, AnonymizationService $anonymization): JsonResponse
    {
        $this->authorize('platform.offboard-schools');

        $data = $request->validate(['mode' => ['required', 'in:anonymize,delete']]);

        if ($data['mode'] === 'delete') {
            $name = $school->name;
            $provisioning->teardown($school);

            return ApiResponse::success(null, "{$name} has been permanently deleted.");
        }

        $school->run(function () use ($anonymization) {
            User::withTrashed()->get()->each(fn (User $user) => $anonymization->anonymizeUser($user));
        });

        $school->update(['is_active' => false, 'billing_status' => 'canceled']);

        return ApiResponse::success(new PlatformSchoolResource($school->fresh('plan')), "{$school->name} has been anonymized and deactivated.");
    }
}
