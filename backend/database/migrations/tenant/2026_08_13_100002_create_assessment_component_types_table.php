<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_component_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            // Only "Online MCQ" ships true — drives whether ExamSubject rows of
            // this type get graded automatically (OnlineExamService) or need a
            // teacher's manual marks entry (ExamService::markBulk()).
            $table->boolean('is_auto_graded')->default(false);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_component_types');
    }
};
