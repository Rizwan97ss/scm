<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionPasswordIsCurrent;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * The other half of SignupController's handoff — see its own docblock.
 * This request lands on the new tenant's own subdomain (tenancy.subdomain
 * middleware has already resolved the right tenant database by the time
 * this runs), so establishing the session here is what actually gets the
 * admin logged in on the correct origin. Reuses the password-reset-token
 * mechanism as a one-time "prove you're this specific pre-provisioned
 * admin" credential rather than inventing a new token table.
 */
class SignupCompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Password::broker()->tokenExists($user, $data['token'])) {
            return ApiResponse::error('This sign-in link is invalid or has expired.', 401);
        }

        Password::broker()->deleteToken($user);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(EnsureSessionPasswordIsCurrent::SESSION_TENANT_KEY, tenant()?->id);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::success(new UserResource($user->load('roles')), 'Signed in.');
    }
}
