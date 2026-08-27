<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_test_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('online_test_attempts')->cascadeOnDelete();
            // tab_hidden|window_blur|fullscreen_exit — an append-only trail an
            // invigilator/teacher can review later; never overwritten, unlike
            // online_test_answers which only keeps the latest selection.
            $table->string('event_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_test_attempt_events');
    }
};
