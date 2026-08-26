<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationController extends Controller
{
    public function notify(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        $key = 'verify-email:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return ApiResponse::error('Please wait before requesting another verification email.', 429);
        }

        RateLimiter::hit($key, 60);

        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Verification email sent.');
    }

    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return ApiResponse::success(null, 'Email verified successfully.');
    }
}
