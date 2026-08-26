<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_active_plans_in_sort_order_without_auth(): void
    {
        Plan::factory()->create(['key' => 'b-plan', 'name' => 'B Plan', 'is_active' => true, 'sort_order' => 2]);
        Plan::factory()->create(['key' => 'a-plan', 'name' => 'A Plan', 'is_active' => true, 'sort_order' => 1]);
        Plan::factory()->create(['key' => 'retired', 'name' => 'Retired Plan', 'is_active' => false, 'sort_order' => 0]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('key');
        $this->assertEquals(['a-plan', 'b-plan'], $keys->all());
    }

    public function test_index_does_not_expose_stripe_ids(): void
    {
        Plan::factory()->create(['stripe_product_id' => 'prod_secret', 'stripe_price_id' => 'price_secret']);

        $response = $this->getJson('/api/v1/plans');

        $response->assertOk();
        $this->assertArrayNotHasKey('stripe_product_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('stripe_price_id', $response->json('data.0'));
    }
}
