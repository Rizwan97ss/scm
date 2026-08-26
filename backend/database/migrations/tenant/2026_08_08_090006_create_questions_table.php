<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('type'); // mcq | true_false — both graded identically via question_options
            $table->text('text');
            $table->decimal('default_marks', 6, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
