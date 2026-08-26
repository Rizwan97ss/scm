<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->decimal('marks', 6, 2)->nullable(); // null = use question.default_marks
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['exam_subject_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_test_questions');
    }
};
