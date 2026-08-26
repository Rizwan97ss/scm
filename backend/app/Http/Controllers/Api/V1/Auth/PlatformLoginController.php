<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PlatformLoginRequest;
use App\Http\Resources\Platform\PlatformUserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PlatformLoginController extends Controller
{
    public function __invoke(PlatformLoginRequest $request): JsonResponse
    {
        $result = $request->authenticate();

        if ($result['mfa_required']) {
            return ApiResponse::success(
                ['mfa_required' => true, 'challenge_token' => $result['challenge_token']],
                'Enter your two-factor authentication code to continue.'
            );
        }

        $request->session()->regenerate();

        $user = Auth::guard('platform')->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::success(new PlatformUserResource($user), 'Logged in successfully.');
    }
}
