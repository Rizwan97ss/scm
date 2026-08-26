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
        $result = $request->authenticate();

        if ($result['mfa_required']) {
            return ApiResponse::success(
                ['mfa_required' => true, 'challenge_token' => $result['challenge_token']],
                'Enter your two-factor authentication code to continue.'
            );
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::success(new UserResource($user->load('roles')), 'Logged in successfully.');
    }
}
