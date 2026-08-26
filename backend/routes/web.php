<?php

use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

// Cashier's own auto-registered routes are disabled (Cashier::ignoreRoutes()
// in AppServiceProvider) so the webhook route can point at our
// StripeWebhookController subclass instead of Cashier's base one. Registered
// under routes/web.php (not api.php) — Stripe calls this server-to-server,
// so it needs neither the api group's Sanctum/EnsureSchoolContext stack nor
// the /api/v1 prefix, just VerifyWebhookSignature (applied automatically by
// the controller's constructor once STRIPE_WEBHOOK_SECRET is set) and a
// CSRF exclusion (see bootstrap/app.php). Keeping the same route names
// Cashier itself would have used ('cashier.payment'/'cashier.webhook') so
// `php artisan cashier:webhook` and the incomplete-payment confirmation flow
// keep working unmodified.
Route::prefix(config('cashier.path'))
    ->name('cashier.')
    ->group(function () {
        Route::get('payment/{id}', [PaymentController::class, 'show'])->name('payment');
        Route::post('webhook', [StripeWebhookController::class, 'handleWebhook'])->name('webhook');
    });
