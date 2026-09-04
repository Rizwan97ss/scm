<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('document');
            // Set for 'link'/'video' materials (an external URL); an uploaded
            // 'document' or video file lives in the 'attachments' media
            // collection instead — see CourseMaterial::registerMediaCollections().
            $table->string('url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['section_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
