<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session;
use Tests\TestCase;

/**
 * SubscriptionService::createCheckoutSession() is mocked throughout — it
 * makes a real Stripe API call, which these tests must not depend on to
 * run deterministically (see docs/testing.md's Phase 6 notes: live Stripe
 * verification is a separate, manual step).
 */
class SignupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A real Checkout instance built locally (Stripe\Checkout\Session::constructFrom
     * populates from a plain array, no API call) — SubscriptionService::createCheckoutSession()
     * is type-hinted to return Checkout, so a stdClass/duck-typed stand-in fails
     * with a TypeError the moment Mockery's generated proxy enforces it.
     */
    private function fakeCheckout(string $url = 'https://checkout.stripe.com/fake-session'): Checkout
    {
        $session = Session::constructFrom(['id' => 'cs_test_fake', 'url' => $url, 'mode' => 'subscription']);

        return new Checkout(null, $session);
    }

    private function payload(array $overrides = []): array
    {
        $plan = Plan::factory()->create(['is_active' => true]);

        return array_merge([
            'school' => [
                'name' => 'New Horizon Academy',
                'short_name' => 'new-horizon',
                'email' => 'info@new-horizon.test',
            ],
            'admin' => [
                'first_name' => 'Nora',
                'last_name' => 'Founder',
                'email' => 'nora@new-horizon.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
            'plan_id' => $plan->id,
        ], $overrides);
    }

    /**
     * Deliberately does NOT log the admin in (see SignupController's own
     * docblock): this request runs on the central domain, a session cookie
     * set here can't carry over to the new tenant's own subdomain. The
     * checkout_url is what the frontend redirects to next; SignupComplete-
     * ControllerTest covers what happens after that (the login-token
     * handoff on the tenant's own subdomain).
     */
    public function test_signup_creates_school_and_admin_and_returns_a_checkout_url(): void
    {
        Notification::fake();
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn($this->fakeCheckout());
        });

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/fake-session')
            ->assertJsonPath('data.school.slug', fn ($slug) => str_starts_with($slug, 'new-horizon'))
            ->assertJsonPath('data.admin.email', 'nora@new-horizon.test');

        $this->assertDatabaseHas('schools', ['short_name' => 'new-horizon']);
        $school = School::query()->where('short_name', 'new-horizon')->first();
        $this->assertNotNull($school);

        $school->run(function () {
            $admin = User::query()->where('email', 'nora@new-horizon.test')->first();
            $this->assertNotNull($admin);
            $this->assertTrue($admin->hasRole('School Admin'));
        });

        // Not logged in — provision() never calls Auth::login(), by design.
        // Checked on the new school's own subdomain (not the central
        // domain this request itself ran on): /api/v1/auth/me is tenant-
        // zone-only, so hitting it from the central domain 404s regardless
        // of auth state — only the tenant's own origin actually exercises
        // "a real tenant, but nobody's authenticated there" (401).
        $central = parse_url(config('app.url'));
        $port = isset($central['port']) ? ':'.$central['port'] : '';
        URL::forceRootUrl("{$central['scheme']}://{$school->slug}.{$central['host']}{$port}");

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertStatus(401);
    }

    public function test_signup_rejects_duplicate_short_name(): void
    {
        School::factory()->create(['short_name' => 'new-horizon']);

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertStatus(422)->assertJsonValidationErrors(['school.short_name']);
    }

    public function test_signup_rejects_an_inactive_plan(): void
    {
        $inactivePlan = Plan::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/signup', $this->payload(['plan_id' => $inactivePlan->id]));

        $response->assertStatus(422)->assertJsonValidationErrors(['plan_id']);
    }

    public function test_signup_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/signup', $this->payload([
            'admin' => [
                'first_name' => 'Nora', 'last_name' => 'Founder', 'email' => 'nora@new-horizon.test',
                'password' => 'password123', 'password_confirmation' => 'does-not-match',
            ],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['admin.password']);
    }

    /**
     * Hard delete, not soft — see SignupController's docblock: a tenant
     * that failed checkout has nothing of value in it yet, and a soft-
     * deleted School whose physical database still exists and is still
     * loginable is a worse failure state than no tenant at all. The
     * physical database itself is dropped too, so there is nothing left
     * to query afterward beyond "the school row doesn't exist".
     */
    public function test_signup_rolls_back_school_and_admin_when_checkout_creation_fails(): void
    {
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')->once()->andThrow(new \RuntimeException('Stripe is down'));
        });

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertStatus(502);

        $this->assertFalse(School::withTrashed()->where('short_name', 'new-horizon')->exists());

        // No tenant ever existed to log into in the first place (hard-
        // deleted above) -- there's no subdomain left to check a session
        // against, so this just confirms the central domain still can't
        // reach the tenant-zone /me route at all (404, not 401 -- see the
        // successful-signup test above for why those differ).
        $me = $this->getJson('/api/v1/auth/me');
        $me->assertStatus(404);
    }
}
