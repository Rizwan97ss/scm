<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_subject_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->nullable()->constrained('grading_scales')->nullOnDelete();
            $table->decimal('passing_marks', 6, 2)->nullable();
            // Declares this specific subject's result for students/parents,
            // independent of (and OR'd with) Exam::is_published — see
            // ExamSubjectGroup::status() and ExamService::reportCard().
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exam_id', 'subject_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subject_groups');
    }
};
