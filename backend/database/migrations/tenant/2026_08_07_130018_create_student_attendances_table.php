<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('timetable_period_id')->nullable()->constrained('timetable_periods')->nullOnDelete();
            $table->date('date');
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            // NULL timetable_period_id means a whole-day record. MySQL/SQLite both treat
            // NULL as distinct in unique indexes, so this alone doesn't stop duplicate
            // daily-mode rows — AttendanceService enforces that via updateOrCreate.
            $table->unique(['student_id', 'date', 'timetable_period_id']);
            $table->index(['date']);
            $table->index(['section_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
