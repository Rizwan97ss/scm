<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
