<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnonymizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service account deletion — no permission gate, the same self-service
 * shape as PasswordController: anyone can remove their own account
 * immediately, reusing the exact anonymization logic an admin-triggered
 * UserController::destroy() uses. No confirmation workflow beyond the
 * request itself (e.g. a re-auth step) for v1 — a reasonable future
 * enhancement, not required scope.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, AnonymizationService $anonymization): JsonResponse
    {
        $user = $request->user();

        $anonymization->anonymizeUser($user);
        $user->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::noContent('Your account has been deleted.');
    }
}
