<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SchoolProvisioningService;
use Illuminate\Console\Command;

/**
 * SchoolProvisioningService::seedDefaultExamConfig() only runs automatically
 * for schools provisioned AFTER Phase 16 — already-live tenants need this
 * run once to pick up the canonical exam types / assessment component
 * types (mirrors permissions:rollout's exact rationale and shape). Safe to
 * re-run: seedDefaultExamConfig() is idempotent (firstOrCreate by code).
 */
class ExamConfigRolloutCommand extends Command
{
    protected $signature = 'exam-config:rollout {--school= : Only roll out to the school with this slug}';

    protected $description = 'Seed the canonical exam types / assessment component types into every existing tenant';

    public function handle(SchoolProvisioningService $provisioning): int
    {
        $slug = $this->option('school');

        $schools = School::query()->when($slug, fn ($q) => $q->where('slug', $slug))->get();

        if ($schools->isEmpty()) {
            $this->error('No matching school found.');

            return self::FAILURE;
        }

        $schools->each(function (School $school) use ($provisioning) {
            // Degrade, don't crash the whole batch — see School::studentCount()'s own guard.
            if (! $school->database()->manager()->databaseExists($school->database()->getName())) {
                $this->warn("  {$school->name}: skipped (no physical database)");

                return;
            }

            $school->run(fn () => $provisioning->seedDefaultExamConfig($school));
            $this->line("  {$school->name}: exam config synced");
        });

        return self::SUCCESS;
    }
}
