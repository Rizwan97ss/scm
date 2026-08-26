<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Null = no negative marking (today's behavior, unchanged) — a
            // wrong answer only ever costs marks when this is explicitly set.
            $table->decimal('negative_marks', 6, 2)->nullable()->after('default_marks');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('negative_marks');
        });
    }
};
