<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // TODO(tenancy): $user->load(['roles', 'school.plan']) -- school.plan relies on a
        // school_id FK on users that no longer exists; needs Sub-phase E's auth/response
        // redesign (tenant() instead of a relation).
        return ApiResponse::success(new UserResource($user->load(['roles', 'school.plan'])), 'Logged in successfully.');
    }
}
