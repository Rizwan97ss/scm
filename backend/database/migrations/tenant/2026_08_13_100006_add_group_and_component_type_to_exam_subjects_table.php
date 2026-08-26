<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            // Nullable for now — the next migration (backfill_exam_subject_groups)
            // populates these for every existing row before a later migration
            // makes them required. See that migration's docblock for why this
            // is split into add -> backfill -> finalize steps instead of one.
            $table->foreignId('exam_subject_group_id')->nullable()->after('exam_id')->constrained('exam_subject_groups')->cascadeOnDelete();
            $table->foreignId('assessment_component_type_id')->nullable()->after('exam_subject_group_id')->constrained('assessment_component_types')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0)->after('assessment_component_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_subject_group_id');
            $table->dropConstrainedForeignId('assessment_component_type_id');
            $table->dropColumn('sequence');
        });
    }
};
