<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Relative, not a strict 0-100 share — TermResultService normalizes
            // by the sum of weights of exams the student actually has results
            // for in that term, so a missing exam (e.g. a mid-year transfer who
            // skipped the Midterm) doesn't need every other exam's weight
            // rebalanced by hand.
            $table->decimal('weight', 5, 2)->default(1)->after('term_id');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
