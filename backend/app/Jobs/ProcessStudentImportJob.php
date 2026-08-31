<?php

namespace App\Jobs;

use App\Enums\ImportLogStatus;
use App\Imports\StudentsImport;
use App\Models\AcademicYear;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Runs a large student-import file on the queue worker instead of inline in
 * the HTTP request — see StudentImportController's file-size branch and
 * docs/deployment.md §5 for the worker itself. Deliberately just a thin
 * wrapper around the exact same StudentsImport class the synchronous path
 * uses (unchanged) rather than a chunked/queued Maatwebsite import: the
 * whole file is still processed start-to-finish in one PHP process (this
 * job's handle()), just on the worker instead of PHP-FPM — so StudentsImport
 * keeps accumulating results in its own instance properties exactly as it
 * always has, no per-row DB writes needed, and the well-tested synchronous
 * behavior (duplicate warnings, guardian attach, undo-tracking) is reused
 * as-is instead of being reimplemented for a second, chunked code path.
 *
 * Tenant-context-aware automatically via stancl's QueueTenancyBootstrapper
 * (config/tenancy.php), same as GenerateDataExportJob — the job payload is
 * tagged with whichever tenant was active at dispatch time and
 * re-initialized on the worker before handle() runs, so tenant() below
 * resolves correctly with no manual $school->run() needed here.
 *
 * tries=1, no automatic retry — see GenerateDataExportJob's identical
 * reasoning: retrying after a partial failure risks re-creating students
 * for rows already committed before the failure point, since there's no
 * per-row resume/idempotency tracking.
 */
class ProcessStudentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly int $importLogId,
        private readonly string $storedFilePath,
        private readonly int $academicYearId,
        private readonly int $performedByUserId,
    ) {}

    public function handle(StudentIdGeneratorService $idGenerator, StudentEnrollmentService $enrollment): void
    {
        $log = ImportLog::query()->find($this->importLogId);

        if (! $log) {
            $this->cleanUp();

            return;
        }

        $log->update(['status' => ImportLogStatus::Processing]);

        try {
            $academicYear = AcademicYear::query()->findOrFail($this->academicYearId);
            $performedBy = User::query()->findOrFail($this->performedByUserId);

            $import = new StudentsImport(tenant(), $academicYear, $performedBy, $idGenerator, $enrollment, dryRun: false);

            Excel::import($import, $this->storedFilePath, 'local');

            $failures = $import->failures()->map(fn ($failure) => [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
            ])->all();

            $log->update([
                'status' => ImportLogStatus::Completed,
                'created_count' => $import->importedCount(),
                'failed_count' => count($failures),
                'failures' => $failures,
                'warnings' => $import->duplicateWarnings(),
            ]);
        } catch (Throwable $e) {
            $log->update(['status' => ImportLogStatus::Failed, 'failure_reason' => substr($e->getMessage(), 0, 500)]);
            $this->cleanUp();

            throw $e;
        }

        $this->cleanUp();
    }

    private function cleanUp(): void
    {
        Storage::disk('local')->delete($this->storedFilePath);
    }
}
