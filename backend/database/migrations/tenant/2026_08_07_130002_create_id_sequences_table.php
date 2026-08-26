<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_sequences');
    }
};
