<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_carry_the_baseline_security_headers(): void
    {
        $response = $this->getJson('/api/v1/plans');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // Explicit absolute http:// URL, not a relative one -- a relative
        // URI resolves against APP_URL (config/app.php), which on this box
        // is the real production https:// URL even under APP_ENV=testing
        // (phpunit.xml deliberately doesn't override it, see its own
        // comment), so a relative getJson() here would silently exercise
        // the *secure* branch instead of the one this test is named for.
        // Mirrors test_hsts_is_sent_once_the_request_is_secure()'s own
        // explicit-URL approach below, just without HTTPS => on.
        $response = $this->call('GET', 'http://localhost/api/v1/plans');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_once_the_request_is_secure(): void
    {
        // A relative "/api/v1/plans" URI resolves against APP_URL (http://…
        // in testing), so the HTTPS server var alone doesn't survive
        // Symfony's Request::create() scheme parsing — the URL itself must
        // be https:// for isSecure() to actually flip. Not an
        // X-Forwarded-Proto header: this app doesn't trust X-Forwarded-* by
        // default (bootstrap/app.php's TRUSTED_PROXIES gate, deliberately
        // unset in testing).
        $response = $this->call('GET', 'https://localhost/api/v1/plans', server: ['HTTPS' => 'on']);

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
