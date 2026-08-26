<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            // exam_id's foreign key relies on the composite unique below as its
            // only backing index (MySQL/InnoDB never created a redundant plain
            // one, since the composite already covered exam_id as its leftmost
            // column) — this plain index must exist first, or MySQL refuses to
            // drop that composite unique out from under the FK constraint.
            $table->index('exam_id');
        });

        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropUnique(['exam_id', 'subject_id', 'section_id']);
        });

        Schema::table('exam_subjects', function (Blueprint $table) {
            // assessment_component_type_id's FK was created SET NULL on delete
            // (see the previous migration) back when the column was still
            // nullable — MySQL refuses to make a SET-NULL-backed column NOT
            // NULL, since it could never actually honor that action again.
            // Swap to RESTRICT before the NOT NULL change below: deleting an
            // in-use component type is now a hard block instead of orphaning
            // the column to null.
            $table->dropForeign(['assessment_component_type_id']);
            $table->foreign('assessment_component_type_id')->references('id')->on('assessment_component_types')->restrictOnDelete();
        });

        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_subject_group_id')->nullable(false)->change();
            $table->unsignedBigInteger('assessment_component_type_id')->nullable(false)->change();
            // Explicit short name — Laravel's auto-generated name for this pair
            // of long column names exceeds MySQL's 64-character identifier limit.
            $table->unique(['exam_subject_group_id', 'assessment_component_type_id'], 'exam_subjects_group_component_unique');
            // Now live only on exam_subject_groups — one grading scale/passing
            // mark per subject-in-section-in-exam, shared by all its components.
            $table->dropConstrainedForeignId('grading_scale_id');
            $table->dropColumn('passing_marks');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->foreignId('grading_scale_id')->nullable()->constrained('grading_scales')->nullOnDelete();
            $table->decimal('passing_marks', 6, 2)->nullable();
            $table->dropUnique('exam_subjects_group_component_unique');
            $table->unsignedBigInteger('exam_subject_group_id')->nullable()->change();
            $table->unsignedBigInteger('assessment_component_type_id')->nullable()->change();
        });

        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropForeign(['assessment_component_type_id']);
            $table->foreign('assessment_component_type_id')->references('id')->on('assessment_component_types')->nullOnDelete();
        });

        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->unique(['exam_id', 'subject_id', 'section_id']);
        });

        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropIndex(['exam_id']);
        });
    }
};
