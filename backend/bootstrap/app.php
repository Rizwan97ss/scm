<?php

use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\EnsureSchoolContext;
use App\Http\Middleware\EnsureSchoolIsUsable;
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
            'school.context' => EnsureSchoolContext::class,
        ]);

        $middleware->appendToGroup('api', EnsureSchoolContext::class);
        $middleware->appendToGroup('api', EnsureSchoolIsUsable::class);

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
