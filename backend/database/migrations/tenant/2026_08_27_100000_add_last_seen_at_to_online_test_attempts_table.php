<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_test_attempts', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('violation_count');
        });
    }

    public function down(): void
    {
        Schema::table('online_test_attempts', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
