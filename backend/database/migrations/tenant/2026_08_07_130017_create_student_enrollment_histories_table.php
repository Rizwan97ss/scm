<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('from_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('to_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('from_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('to_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->date('effective_date');
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollment_histories');
    }
};
