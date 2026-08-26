<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\OnlineExamService;
use Illuminate\Console\Command;

/**
 * Server-side backstop for online-test attempts nobody ever submitted (a
 * closed tab, a lost connection) — the client-side countdown in
 * TakeOnlineTestPage.tsx is the primary mechanism for a student still on
 * the page, this catches everything past its window that timer never got
 * to run for. See OnlineExamService::autoSubmitExpired().
 */
class AutoSubmitExpiredOnlineTestsCommand extends Command
{
    protected $signature = 'exams:auto-submit-expired';

    protected $description = 'Auto-submit online-test attempts still in_progress after their exam subject\'s online_ends_at, in every tenant database';

    public function handle(OnlineExamService $onlineExams): int
    {
        School::all()->each(function (School $school) use ($onlineExams) {
            // Degrade, don't crash the whole batch — see School::studentCount()'s own guard.
            if (! $school->database()->manager()->databaseExists($school->database()->getName())) {
                return;
            }

            $submitted = $school->run(fn () => $onlineExams->autoSubmitExpired());

            if ($submitted > 0) {
                $this->line("  {$school->name}: {$submitted} expired attempt(s) auto-submitted");
            }
        });

        return self::SUCCESS;
    }
}
