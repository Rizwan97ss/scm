<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('mixed');
            $table->string('address')->nullable();
            $table->string('warden_name')->nullable();
            $table->string('warden_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostels');
    }
};
