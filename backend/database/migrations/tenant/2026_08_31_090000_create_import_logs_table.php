<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('mode');
            $table->string('status')->default('completed');
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('undone_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->json('failures')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index(['entity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
