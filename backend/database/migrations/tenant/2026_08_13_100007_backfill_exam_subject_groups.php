<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for the exam_subject_groups split: every ExamSubject
 * row that existed before this migration represented exactly one
 * component of exactly one subject-in-section-in-exam. This creates the
 * matching group row and points the ExamSubject at it, so
 * finalize_exam_subjects_group_columns can safely make those FK columns
 * required afterward.
 *
 * Only bootstraps the two component types this backfill itself needs
 * (Online MCQ, Written) — the full canonical set (+ Practical, Oral/Viva,
 * and all exam types) is seeded per-tenant by SchoolProvisioningService /
 * backfilled into already-live tenants by the exam-config:rollout command,
 * matching this codebase's established convention that tenant migrations
 * never seed rows themselves.
 *
 * One-way: down() does not attempt to reconstruct the pre-split shape —
 * same as this codebase's other data migrations (e.g. widen_pii_columns_
 * for_encryption's docblock makes the same call).
 */
return new class extends Migration
{
    public function up(): void
    {
        $onlineMcqId = DB::table('assessment_component_types')->where('code', 'online_mcq')->value('id');
        if (! $onlineMcqId) {
            $onlineMcqId = DB::table('assessment_component_types')->insertGetId([
                'name' => 'Online MCQ', 'code' => 'online_mcq', 'is_auto_graded' => true, 'sequence' => 1,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $writtenId = DB::table('assessment_component_types')->where('code', 'written')->value('id');
        if (! $writtenId) {
            $writtenId = DB::table('assessment_component_types')->insertGetId([
                'name' => 'Written', 'code' => 'written', 'is_auto_graded' => false, 'sequence' => 2,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('exam_subjects')->orderBy('id')->chunkById(200, function ($rows) use ($onlineMcqId, $writtenId) {
            foreach ($rows as $row) {
                $groupId = DB::table('exam_subject_groups')->insertGetId([
                    'exam_id' => $row->exam_id,
                    'subject_id' => $row->subject_id,
                    'section_id' => $row->section_id,
                    'grading_scale_id' => $row->grading_scale_id,
                    'passing_marks' => $row->passing_marks,
                    'created_at' => $row->created_at,
                    'updated_at' => now(),
                ]);

                DB::table('exam_subjects')->where('id', $row->id)->update([
                    'exam_subject_group_id' => $groupId,
                    'assessment_component_type_id' => $row->is_online ? $onlineMcqId : $writtenId,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Intentionally a no-op — see docblock above.
    }
};
