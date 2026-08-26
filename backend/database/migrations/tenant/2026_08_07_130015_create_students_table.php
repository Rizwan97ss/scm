<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('admission_number');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('blood_group')->nullable();
            $table->string('nationality')->nullable();
            $table->foreignId('current_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('current_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('roll_number')->nullable();
            $table->date('admission_date');
            $table->string('status')->default('active');
            $table->string('previous_school_name')->nullable();
            $table->text('previous_school_details')->nullable();
            $table->text('medical_info')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['admission_number']);
            $table->index(['status']);
            $table->index(['current_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
