<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_material_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_material_id')->constrained('course_materials')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_material_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_material_progress');
    }
};
