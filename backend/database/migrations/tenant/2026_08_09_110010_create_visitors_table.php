<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('purpose');
            $table->string('whom_to_meet')->nullable();
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['check_in_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
