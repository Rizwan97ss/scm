<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('max_percentage', 5, 2);
            $table->string('grade_label');
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->index(['grading_scale_id', 'min_percentage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
    }
};
