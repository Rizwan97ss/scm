<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('all');
            $table->json('channels');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
