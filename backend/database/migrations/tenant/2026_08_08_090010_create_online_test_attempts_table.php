<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status')->default('in_progress'); // in_progress|submitted
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->timestamps();
            // Explicit short name: the auto-generated one (table + all three
            // column names + "_unique") is 69 chars, over MySQL's 64-char
            // identifier limit — SQLite has no such limit, so this only
            // surfaces once a real MySQL database is migrated against.
            $table->unique(['exam_subject_id', 'student_id', 'attempt_number'], 'online_test_attempts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_test_attempts');
    }
};
