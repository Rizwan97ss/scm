<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->string('status')->default('upcoming');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
