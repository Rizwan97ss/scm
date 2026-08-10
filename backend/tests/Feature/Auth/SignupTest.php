<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

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

    public function test_signup_creates_school_and_admin_and_logs_them_in(): void
    {
        Notification::fake();
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn($this->fakeCheckout());
        });

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/fake-session')
            ->assertJsonPath('data.user.email', 'nora@new-horizon.test')
            ->assertJsonPath('data.user.roles.0', 'School Admin');

        $this->assertDatabaseHas('schools', ['short_name' => 'new-horizon']);
        $school = School::query()->where('short_name', 'new-horizon')->first();
        $this->assertNotNull($school);

        $admin = User::query()->where('email', 'nora@new-horizon.test')->first();
        $this->assertEquals($school->id, $admin->school_id);

        // Logged in immediately — /auth/me should resolve without a
        // separate login call.
        $me = $this->getJson('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('data.email', 'nora@new-horizon.test');
    }

    public function test_signup_rejects_duplicate_short_name(): void
    {
        School::factory()->create(['short_name' => 'new-horizon']);

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertStatus(422)->assertJsonValidationErrors(['school.short_name']);
    }

    public function test_signup_rejects_duplicate_admin_email(): void
    {
        User::factory()->create(['email' => 'nora@new-horizon.test']);

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertStatus(422)->assertJsonValidationErrors(['admin.email']);
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

    public function test_signup_rolls_back_school_and_admin_when_checkout_creation_fails(): void
    {
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')->once()->andThrow(new \RuntimeException('Stripe is down'));
        });

        $response = $this->postJson('/api/v1/auth/signup', $this->payload());

        $response->assertStatus(502);

        $school = School::withTrashed()->where('short_name', 'new-horizon')->first();
        $this->assertNotNull($school);
        $this->assertSoftDeleted($school);

        $admin = User::withTrashed()->where('email', 'nora@new-horizon.test')->first();
        $this->assertNotNull($admin);
        $this->assertSoftDeleted($admin);

        // Not left logged in as a user tied to a rolled-back school.
        $me = $this->getJson('/api/v1/auth/me');
        $me->assertStatus(401);
    }
}
