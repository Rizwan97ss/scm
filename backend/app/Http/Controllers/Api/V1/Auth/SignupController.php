<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\Plan;
use App\Models\School;
use App\Services\SchoolProvisioningService;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignupController extends Controller
{
    public function __construct(
        private readonly SchoolProvisioningService $provisioning,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * This request runs on the central/signup domain, where no tenant is
     * (or can be) resolved yet — the school doesn't exist until
     * provision() returns. That matters for what happens after: a session
     * cookie set here would be scoped to the central domain (SESSION_DOMAIN
     * is null/exact-host by design, see docs/deployment.md's Sub-phase B
     * notes) and would NOT be sent by the browser once redirected to the
     * new tenant's own subdomain. So this controller deliberately does not
     * log the admin in — provision() instead returns a one-time login
     * token (a password-reset-broker token, reused rather than inventing a
     * new mechanism) embedded in Stripe's success_url, which points at the
     * tenant's own subdomain. SignupCompleteController there is what
     * actually establishes the session, on the correct origin.
     */
    public function __invoke(SignupRequest $request): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        $provisioned = $this->provisioning->provision(
            $request->validated('school'),
            $request->validated('admin'),
            $plan
        );

        /** @var School $school */
        $school = $provisioned['school'];
        $admin = $provisioned['admin'];
        $loginToken = $provisioned['login_token'];

        // TODO(tenancy): email verification deliberately skipped here for
        // now -- VerifyEmail::createUrlUsing() (AppServiceProvider) builds
        // its signed URL against the CURRENT request's host (this central
        // domain), not the tenant subdomain the link actually needs to
        // point at, and that route also requires auth:sanctum, which the
        // admin doesn't have yet at this point in the flow. Needs an
        // explicit host override, not a guess -- sending a broken link
        // would be worse than sending none.
        $successUrl = $school->frontendUrl().'/signup/complete?'.http_build_query([
            'email' => $admin->email,
            'token' => $loginToken,
        ]);
        $cancelUrl = rtrim(config('app.frontend_url'), '/').'/signup';

        try {
            $checkout = $this->subscriptions->createCheckoutSession($school, $plan, $successUrl, $cancelUrl);
        } catch (Throwable $e) {
            Log::error('Signup Stripe Checkout creation failed, dropping provisioned tenant', [
                'school_id' => $school->id,
                'school_slug' => $school->slug,
                'exception' => $e->getMessage(),
            ]);

            // Compensating action, not a DB transaction rollback — provision()
            // already committed (landlord row + a real physical tenant
            // database) before this external Stripe call, which can't safely
            // hold a transaction open across it. Hard delete, not soft: a
            // soft-deleted School whose tenant database still physically
            // exists and is still loginable is a worse failure state than
            // no tenant at all, and nothing of value exists in it yet.
            $school->database()->manager()->deleteDatabase($school);
            $school->forceDelete();

            return ApiResponse::error('We could not start checkout. Please try again.', 502);
        }

        return ApiResponse::created([
            'school' => ['id' => $school->id, 'name' => $school->name, 'slug' => $school->slug],
            'admin' => ['id' => $admin->id, 'first_name' => $admin->first_name, 'last_name' => $admin->last_name, 'email' => $admin->email],
            'checkout_url' => $checkout->url,
        ], 'Account created — complete checkout to activate your trial.');
    }
}
