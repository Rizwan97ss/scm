<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Services\SchoolProvisioningService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class SignupController extends Controller
{
    public function __construct(
        private readonly SchoolProvisioningService $provisioning,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function __invoke(SignupRequest $request): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $school = $this->provisioning->provision(
            $request->validated('school'),
            $request->validated('admin'),
            $plan
        );

        $admin = $school->users()->first();

        // The new admin lands authenticated immediately — taking them to
        // Stripe is a checkout step, not a login gate. EnsureSchoolContext
        // ran before this request had a user, so the permission team id
        // must be set again here, same as LoginController.
        Auth::login($admin);
        $request->session()->regenerate();
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $admin->sendEmailVerificationNotification();

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        try {
            $checkout = $this->subscriptions->createCheckoutSession(
                $school,
                $plan,
                "{$frontendUrl}/signup/complete",
                "{$frontendUrl}/signup"
            );
        } catch (Throwable $e) {
            Log::error('Signup Stripe Checkout creation failed, rolling back provisioned tenant', [
                'school_id' => $school->id,
                'exception' => $e->getMessage(),
            ]);

            // Compensating action, not a DB transaction rollback —
            // provision() already committed before this external Stripe
            // call, which can't safely hold that transaction open. Soft
            // deletes leave the school's roles/permission rows orphaned
            // under its team id, which is harmless (SchoolScope excludes
            // the soft-deleted school from every query) and left for a
            // future cleanup job rather than deep-cleaned here.
            $admin->delete();
            $school->delete();

            Auth::logout();
            $request->session()->invalidate();

            return ApiResponse::error('We could not start checkout. Please try again.', 502);
        }

        return ApiResponse::created([
            'user' => new UserResource($admin->load(['roles', 'school.plan'])),
            'checkout_url' => $checkout->url,
        ], 'Account created — complete checkout to activate your trial.');
    }
}
