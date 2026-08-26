<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Always responds with a generic success message regardless of whether the
     * email exists, to avoid leaking account existence (user enumeration).
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if (! in_array($status, [PasswordBroker::RESET_LINK_SENT, PasswordBroker::INVALID_USER], true)) {
            return ApiResponse::error('Unable to process the request right now. Please try again shortly.', 429);
        }

        return ApiResponse::success(null, 'If an account exists for that email, a password reset link has been sent.');
    }
}
