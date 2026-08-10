<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('timetable_period_id')->constrained('timetable_periods')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamps();

            $table->unique(['section_id', 'timetable_period_id', 'day_of_week'], 'timetable_entries_section_slot_unique');
            $table->index(['teacher_id', 'timetable_period_id', 'day_of_week'], 'timetable_entries_teacher_slot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
