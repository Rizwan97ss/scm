<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('early_access_minutes')->default(5)->after('max_attempts');
            $table->unsignedSmallInteger('late_join_grace_minutes')->default(2)->after('early_access_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropColumn(['early_access_minutes', 'late_join_grace_minutes']);
        });
    }
};
