<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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

        $actor = $request->user();

        // TODO(tenancy): Super Admin detection needs PlatformUser (Sub-phase E) -- do not guess at a replacement.
        $paginator = QueryBuilder::for(User::query()->with(['roles', 'designation']))
            ->when($actor->school_id === null && $request->filled('school_id'), fn ($q) => $q->inSchool($request->integer('school_id')))
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
        $this->authorize('create', User::class);

        $actor = $request->user();

        // Only roles that actually count toward a "staff seat" (see
        // School::staffCount()) trigger the check — a user created here
        // with only Student/Parent roles doesn't consume one.
        $isStaffRole = ! empty(array_diff($request->array('roles'), ['Student', 'Parent']));
        // TODO(tenancy): Super Admin detection needs PlatformUser (Sub-phase E) -- do not guess at a replacement.
        if ($actor->school_id !== null && $isStaffRole) {
            $planLimits->assertCanAddStaffUser($actor->school);
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

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return ApiResponse::noContent();
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', [User::class, $user]);

        $user->syncRoles($request->array('roles'));

        return ApiResponse::success(new UserResource($user->load('roles')), 'Roles updated.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

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
}
