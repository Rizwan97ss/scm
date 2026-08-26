<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('certificate_template_id')->constrained('certificate_templates')->restrictOnDelete();
            $table->string('certificate_number');
            $table->date('issued_date');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->unique(['certificate_number']);
            $table->index(['student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
