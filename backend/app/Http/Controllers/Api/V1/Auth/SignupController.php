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
use Throwable;

class SignupController extends Controller
{
    public function __construct(
        private readonly SchoolProvisioningService $provisioning,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * TODO(tenancy): this entire flow needs Sub-phase E's rewrite, not a
     * patch — $this->provisioning->provision() and $school->users()->first()
     * both still assume the old single-database shape (a School row's admin
     * User is reachable via a school_id FK). The real order becomes: create
     * School (landlord) -> synchronously provision + migrate its physical
     * tenant database -> create the admin User INSIDE that tenant context.
     * Only the now-pointless setPermissionsTeamId() call (teams is off) was
     * removed here; everything else is left as a known-broken placeholder.
     */
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
        // Stripe is a checkout step, not a login gate.
        Auth::login($admin);
        $request->session()->regenerate();

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
            // call, which can't safely hold that transaction open.
            // TODO(tenancy): once Sub-phase E's real flow lands, this needs
            // to hard-delete-and-drop the tenant database on failure, not
            // soft-delete — see docs/roadmap.md Phase 14 plan notes.
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
