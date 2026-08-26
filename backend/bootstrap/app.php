<?php

use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\EnsureMfaEnrolled;
use App\Http\Middleware\EnsureSchoolIsUsable;
use App\Http\Middleware\EnsureSessionPasswordIsCurrent;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();

        // This is an API-only app (no named 'login' route exists — it's an
        // SPA route, not a Laravel one). Left at Laravel's default, the auth
        // middleware tries to redirect any *non*-JSON-expecting unauthenticated
        // request (a plain browser navigation — exactly what a PDF download
        // link is, unlike the SPA's own XHR calls) to route('login'), which
        // throws RouteNotFoundException instead of the intended 401. Forcing
        // a null redirect here means an expired/invalid session on a PDF
        // link now correctly reaches the AuthenticationException render
        // handler below (clean JSON 401) instead of crashing with a 500.
        $middleware->redirectGuestsTo(null);

        $middleware->append(SecurityHeaders::class);

        // Unset by default — a Laravel app with no reverse proxy in front of it
        // (e.g. this app's own `php artisan serve` in local dev) must NOT trust
        // X-Forwarded-* headers, since anyone could spoof them to fake their IP
        // or scheme. Set to the proxy's IP(s) (comma-separated) or '*' once a
        // real reverse proxy sits in front — see deployment.md § 4 — otherwise
        // isSecure()/ip() detection (and this middleware's own HSTS header)
        // silently misbehave behind one.
        if ($trustedProxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : explode(',', $trustedProxies));
        }

        $middleware->alias([
            // Not yet attached to any route group — Sub-phase E splits
            // routes.php into a tenant-facing group (gets this) vs a
            // platform-facing one (must never get it, since Super Admin
            // isn't inside any tenant). Applying it globally now would break
            // every request to the central/platform domain: its default
            // failure mode on an unrecognized-as-tenant host is to throw,
            // not pass through.
            'tenancy.subdomain' => InitializeTenancyBySubdomain::class,
        ]);

        // EnsureSchoolContext (Spatie permission "team" context) was deleted
        // in Sub-phase D — teams is off now that physical DB separation
        // replaces it. EnsureSchoolIsUsable stays: it now reads tenant()
        // instead of $user->school (see its own docblock).
        $middleware->appendToGroup('api', EnsureSchoolIsUsable::class);

        // A suspended/inactive tenant user's existing session previously
        // kept working indefinitely — LoginRequest only ever checked
        // UserStatus once, at the moment of login. This re-checks on every
        // authenticated request and kills the session the moment status
        // stops being Active. No-ops for PlatformUser (no status column —
        // Super Admin accounts aren't suspendable this way) and for guests.
        $middleware->appendToGroup('api', EnsureUserIsActive::class);

        // Invalidates a session the moment its stored password hash no
        // longer matches the user's current one — see its own docblock for
        // why this is a global registration rather than added per
        // route-group. Runs for both guards (tenant and platform).
        $middleware->appendToGroup('api', EnsureSessionPasswordIsCurrent::class);

        // Phase 15 — mandatory MFA. Reads $request->user(), so it needs
        // auth:sanctum/auth:platform (route-group middleware) to have
        // already resolved a user; no-ops for guest routes (login, the MFA
        // challenge endpoint itself) since $request->user() is null there.
        // See EnsureMfaEnrolled's own docblock for why one class covers
        // both guards.
        $middleware->appendToGroup('api', EnsureMfaEnrolled::class);

        // Stripe's webhook POSTs are server-to-server — no session, no CSRF
        // token. Signature verification (VerifyWebhookSignature, applied by
        // StripeWebhookController once STRIPE_WEBHOOK_SECRET is set) is the
        // actual authenticity check for this route. Matches config/cashier.php's
        // 'path' (CASHIER_PATH env, default 'stripe') — update both together
        // if that's ever customized.
        $middleware->validateCsrfTokens(except: ['stripe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'This action is unauthorized.', 403);
            }
        });

        $exceptions->render(function (PlanLimitExceededException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 402);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found.', 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found.', 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'An error occurred.', $e->getStatusCode());
            }
        });
    })->create();
