<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Guardian::class);

        $paginator = QueryBuilder::for(Guardian::query()->with('students')->visibleTo($request->user()))
            ->allowedFilters('first_name', 'last_name', 'email', 'phone')
            ->allowedSorts('first_name', 'last_name', 'created_at')
            ->defaultSort('first_name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(GuardianResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function show(Guardian $guardian): JsonResponse
    {
        $this->authorize('view', $guardian);

        return ApiResponse::success(new GuardianResource($guardian->load('students')));
    }

    /**
     * Creates (or reuses) a portal login and emails the guardian a link to set
     * their password — guardians realistically have a real inbox, unlike students.
     */
    public function invite(Guardian $guardian): JsonResponse
    {
        $this->authorize('invite', $guardian);

        if (! $guardian->email) {
            throw ValidationException::withMessages(['email' => 'This guardian has no email on file to invite.']);
        }

        if (! $guardian->user_id) {
            $user = User::query()->firstOrCreate(
                ['email' => $guardian->email],
                [
                    'first_name' => $guardian->first_name,
                    'last_name' => $guardian->last_name,
                    'phone' => $guardian->phone,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                ]
            );

            if (! $user->hasRole('Parent')) {
                $user->assignRole('Parent');
            }

            $guardian->update(['user_id' => $user->id, 'invited_at' => now()]);
        } else {
            $guardian->update(['invited_at' => now()]);
        }

        $status = Password::sendResetLink(['email' => $guardian->email]);

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            return ApiResponse::error('Unable to send the invite right now.', 500);
        }

        return ApiResponse::success(null, 'Invite sent to the guardian.');
    }
}
