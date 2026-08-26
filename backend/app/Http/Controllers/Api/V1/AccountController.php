<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnonymizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Self-service account deletion — no permission gate, the same self-service
 * shape as PasswordController: anyone can remove their own account
 * immediately, reusing the exact anonymization logic an admin-triggered
 * UserController::destroy() uses. Password-confirmed, the same rule
 * MfaController::regenerateRecoveryCodes() uses — this action is
 * irreversible, so a hijacked/idle session (XSS, shared device, unattended
 * browser) shouldn't be able to destroy the account on session cookie
 * alone.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, AnonymizationService $anonymization): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        $anonymization->anonymizeUser($user);
        $user->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::noContent('Your account has been deleted.');
    }
}
