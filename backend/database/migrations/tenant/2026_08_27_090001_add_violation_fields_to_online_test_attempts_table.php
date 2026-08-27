<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_test_attempts', function (Blueprint $table) {
            // null for a genuine manual submit; 'time_expired' or 'violation'
            // when submitAttempt() was invoked on the student's behalf rather
            // than by their own explicit Submit click.
            $table->string('auto_submit_reason')->nullable()->after('status');
            $table->unsignedSmallInteger('violation_count')->default(0)->after('auto_submit_reason');
        });
    }

    public function down(): void
    {
        Schema::table('online_test_attempts', function (Blueprint $table) {
            $table->dropColumn(['auto_submit_reason', 'violation_count']);
        });
    }
};
