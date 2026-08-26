<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AnonymizationService;
use App\Services\PlanLimitService;
use App\Support\ApiResponse;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        // Super Admin (PlatformUser) can't reach this tenant-scoped route at
        // all, so there's no cross-tenant "?school_id=" filter branch to
        // support here anymore — every request here already sees exactly
        // one tenant's users, by construction.
        $paginator = QueryBuilder::for(User::query()->with(['roles', 'designation']))
            ->allowedFilters('first_name', 'last_name', 'email', 'status', AllowedFilter::exact('role', 'roles.name'))
            ->allowedSorts('first_name', 'last_name', 'email', 'created_at')
            ->defaultSort('first_name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(UserResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(StoreUserRequest $request, PlanLimitService $planLimits): JsonResponse
    {
        $this->authorize('create', [User::class, $request->array('roles')]);

        // Only roles that actually count toward a "staff seat" (see
        // School::staffCount()) trigger the check — a user created here
        // with only Student/Parent roles doesn't consume one. Super Admin
        // (PlatformUser) can't reach this tenant-scoped route at all, so no
        // bypass is needed here.
        $isStaffRole = ! empty(array_diff($request->array('roles'), ['Student', 'Parent']));
        if ($isStaffRole) {
            $planLimits->assertCanAddStaffUser(tenant());
        }

        $user = User::query()->create([
            ...$request->safe()->except(['roles', 'password_confirmation']),
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        $user->syncRoles($request->array('roles'));

        return ApiResponse::created(new UserResource($user->load('roles')));
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(new UserResource($user->load(['roles', 'school', 'designation'])));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->update($request->validated());

        return ApiResponse::success(new UserResource($user->load(['roles', 'designation'])));
    }

    /**
     * Anonymizes PII (see AnonymizationService's docblock for exactly what
     * survives) before soft-deleting — a real behavior change from the
     * previous bare soft-delete: restoring this user now brings back an
     * anonymized shell, not their original data. See docs/rbac.md.
     */
    public function destroy(User $user, AnonymizationService $anonymization): JsonResponse
    {
        $this->authorize('delete', $user);

        $anonymization->anonymizeUser($user);
        $user->delete();

        return ApiResponse::noContent();
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', [User::class, $user, $request->array('roles')]);

        $user->syncRoles($request->array('roles'));

        return ApiResponse::success(new UserResource($user->load('roles')), 'Roles updated.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorize('updateStatus', $user);

        $user->update(['status' => $request->validated('status')]);

        return ApiResponse::success(new UserResource($user->load('roles')), 'Status updated.');
    }

    /**
     * Admin-triggered reset: sends the same signed reset-password email a user
     * would request themselves, rather than returning a plaintext password.
     */
    public function resetPassword(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            return ApiResponse::error('Unable to send the reset link right now.', 500);
        }

        return ApiResponse::success(null, 'Password reset link sent to the user.');
    }

    /**
     * Clears a user's TOTP enrollment entirely (lost device, no working
     * recovery code) and grants a short grace period so they aren't
     * instantly locked out by EnsureMfaEnrolled on their very next request.
     * Gated on the dedicated 'users.manage-mfa' permission, not the
     * self-service-friendly UserPolicy::update() ('users.edit' OR "it's my
     * own account") — a user resetting their OWN MFA with no second factor
     * at all would defeat the entire point of it being mandatory.
     */
    public function resetMfa(User $user): JsonResponse
    {
        $this->authorize('users.manage-mfa');

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'mfa_grace_period_ends_at' => now()->addDays(3),
        ])->save();

        return ApiResponse::success(null, "Two-factor authentication has been reset for {$user->full_name}.");
    }
}
