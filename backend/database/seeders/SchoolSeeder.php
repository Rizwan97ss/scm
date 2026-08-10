<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        School::query()->firstOrCreate(
            ['short_name' => 'demo'],
            [
                'name' => 'Riverside Demo School',
                'slug' => 'riverside-demo-school',
                'email' => 'info@riverside-demo.test',
                'phone' => '+1-555-0100',
                'address_line1' => '123 Riverside Avenue',
                'city' => 'Springfield',
                'state' => 'IL',
                'postal_code' => '62701',
                'country' => 'US',
                'timezone' => 'America/Chicago',
                'locale' => 'en',
                'is_active' => true,
                'plan_id' => Plan::query()->where('key', 'growth')->value('id'),
                'billing_status' => 'active',
            ]
        );
    }
}
