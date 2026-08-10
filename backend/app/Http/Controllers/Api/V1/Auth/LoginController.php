<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\PermissionRegistrar;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // EnsureSchoolContext already ran before Auth::attempt() resolved the user
        // on this specific request, so the permission team context must be set here too.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id ?? 0);

        return ApiResponse::success(new UserResource($user->load(['roles', 'school.plan'])), 'Logged in successfully.');
    }
}
