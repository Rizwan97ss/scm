<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->string('scope'); // 'self' | 'school' — see App\Enums\DataExportScope
            $table->string('status')->default('pending'); // see App\Enums\DataExportStatus
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
