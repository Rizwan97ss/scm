<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('is_active')
                ->constrained()->restrictOnDelete();
            // Cashier's Billable trait columns — kept as plain nullable strings
            // rather than published via Cashier's own migration, since this
            // migration runs in Sub-phase A, before Cashier is installed.
            $table->string('stripe_id')->nullable()->unique()->after('plan_id');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
            // Stores Stripe's own raw subscription status string directly
            // (trialing/active/past_due/canceled/unpaid/...) rather than a
            // custom enum, so the webhook sync in Sub-phase B is a plain
            // pass-through instead of a translation layer that can drift.
            $table->string('billing_status')->nullable()->index()->after('trial_ends_at');
        });

        // Schools that existed before this SaaS layer were never meant to
        // pay — grandfather them in as active so Sub-phase E's access gate
        // doesn't lock out pre-existing tenants.
        DB::table('schools')->whereNull('deleted_at')->update(['billing_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at', 'billing_status']);
        });
    }
};
