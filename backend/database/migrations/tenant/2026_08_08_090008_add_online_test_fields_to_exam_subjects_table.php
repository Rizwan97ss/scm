<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('exam_date');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('is_online');
            $table->timestamp('online_starts_at')->nullable()->after('duration_minutes');
            $table->timestamp('online_ends_at')->nullable()->after('online_starts_at');
            $table->boolean('shuffle_questions')->default(true)->after('online_ends_at');
            $table->unsignedTinyInteger('max_attempts')->default(1)->after('shuffle_questions');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'duration_minutes', 'online_starts_at', 'online_ends_at', 'shuffle_questions', 'max_attempts']);
        });
    }
};
