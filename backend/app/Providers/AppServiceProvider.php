<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\PushGatewayInterface;
use App\Contracts\SmsGatewayInterface;
use App\Models\Platform\PlatformUser;
use App\Models\School;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\RolePolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // See config/payments.php — swapping the school-to-parent fee
        // payment gateway (Phase 8) is a one-line config change, not a
        // rewrite of anything that depends on PaymentGatewayInterface.
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gatewayClass = config('payments.gateways.'.config('payments.default'));

            return $app->make($gatewayClass);
        });

        // See config/communication.php — same swappable-seam shape as
        // PaymentGatewayInterface above, for Phase 11's SMS/push channels.
        $this->app->bind(SmsGatewayInterface::class, function ($app) {
            $gatewayClass = config('communication.sms_gateways.'.config('communication.sms_default'));

            return $app->make($gatewayClass);
        });

        $this->app->bind(PushGatewayInterface::class, function ($app) {
            $gatewayClass = config('communication.push_gateways.'.config('communication.push_default'));

            return $app->make($gatewayClass);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gated on production rather than applied unconditionally: uncompromised()
        // makes a live call to the Have I Been Pwned API on every password
        // submission, which would slow down (and risk flaking) both the test
        // suite and local dev signup/reset flows for no benefit there. Every
        // Password::defaults() call site (SignupRequest, ResetPasswordRequest,
        // UpdatePasswordRequest, StoreUserRequest) picks this up automatically.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        // A backstop behind the narrowly-scoped throttles already on
        // login/signup/password-reset (routes/api.php) — those stay, this
        // just bounds every other endpoint (reports, search, exports, etc.)
        // that had no limit at all. Skipped in testing: `array` cache persists
        // for the whole phpunit process (phpunit.xml), so a real limit here
        // would accumulate across all 245+ tests sharing the same IP/user
        // rather than resetting per test, and fail the suite on unrelated runs.
        RateLimiter::for('api', function (Request $request) {
            return $this->app->environment('testing')
                ? Limit::none()
                : Limit::perMinute(150)->by($request->user()?->id ?: $request->ip());
        });

        // Super Admin bypasses every permission check. Untyped/Authenticatable
        // param deliberately, not User or PlatformUser specifically -- Gate's
        // before-callbacks are invoked for whichever guard resolved the
        // current request's user (canBeCalledWithUser() does no type
        // filtering of its own), so a narrower type-hint here would throw a
        // TypeError the first time this runs against the other user type.
        // TODO(tenancy): the tenant-User branch below is dead code until/
        // unless a real cross-tenant tenant-side use case needs it --
        // Super Admin is PlatformUser now (Sub-phase E wires up the guard
        // switch via Auth::shouldUse('platform') on platform-only routes).
        Gate::before(function (Authenticatable $user) {
            if ($user instanceof PlatformUser) {
                return true;
            }

            return $user instanceof User && $user->school_id === null && $user->hasRole('Super Admin') ? true : null;
        });

        // Spatie's Role/Activity models live outside App\Models, so policy auto-discovery can't find them.
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, AuditLogPolicy::class);

        // Our verification route is named api.v1.auth.verification.verify (nested under
        // the v1/auth route group), not Laravel's default "verification.verify".
        VerifyEmail::createUrlUsing(function (User $notifiable) {
            return URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );
        });

        // The reset link is opened in the browser, so it must point at the React
        // SPA's reset-password page, not this JSON API. The SPA then submits the
        // token/email/new password to POST /api/v1/auth/reset-password.
        ResetPassword::createUrlUsing(function (User $notifiable, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset());
        });

        // Billing is per-school, not per-user (a founding admin can leave;
        // the school keeps paying) — see docs/database.md's Phase 6 notes.
        Cashier::useCustomerModel(School::class);

        // Cashier's own auto-registered webhook route binds its base
        // WebhookController, not our StripeWebhookController subclass (which
        // syncs schools.billing_status on top of Cashier's own handling) —
        // routes/api.php registers ours manually instead.
        Cashier::ignoreRoutes();
    }
}
