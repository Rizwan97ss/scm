<?php

namespace App\Support;

use App\Enums\ImportLogStatus;
use App\Models\ImportLog;
use App\Models\User;

/**
 * Writes one row per import attempt (dry runs included, so a School Admin
 * can see "who's been experimenting" as well as "who actually imported
 * what") — the piece that was previously missing: every import returned
 * imported_count/failed_count in its own HTTP response and then that
 * information was gone forever. Deliberately a thin service, not a model
 * event/observer — only the import controllers call this, so there's no
 * value in making it implicit.
 */
class ImportLogger
{
    /**
     * The synchronous case: the import already finished by the time this is
     * called, so the row is written as Completed in one shot.
     *
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $createdModels  The specific rows this import created (never updated rows — see SimpleLookupImport::createdModels()), recorded so ImportUndoService can later reverse exactly this batch. Ignored for dry runs, since nothing was actually written.
     * @param  array<int, array{row: int, attribute: string, errors: array<int, string>}>  $failures
     * @param  array<int, array{row: int, message: string}>  $warnings
     */
    public function log(
        string $entity,
        ?User $performedBy,
        string $fileName,
        string $mode,
        bool $dryRun,
        int $createdCount,
        int $updatedCount,
        int $failedCount,
        array $createdModels = [],
        array $failures = [],
        array $warnings = [],
    ): ImportLog {
        $log = ImportLog::query()->create([
            'entity' => $entity,
            'performed_by' => $performedBy?->id,
            'file_name' => $fileName,
            'mode' => $mode,
            'dry_run' => $dryRun,
            'status' => ImportLogStatus::Completed,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'failed_count' => $failedCount,
            'failures' => $failures,
            'warnings' => $warnings,
        ]);

        if (! $dryRun && $createdModels) {
            $log->items()->createMany(array_map(
                fn ($model) => ['model_type' => get_class($model), 'model_id' => $model->getKey()],
                $createdModels,
            ));
        }

        return $log;
    }

    /**
     * The queued case: nothing has run yet — ProcessStudentImportJob fills
     * in the real counts/status once it actually processes the file on the
     * worker. See StudentImportController's file-size branch.
     */
    public function logQueued(string $entity, ?User $performedBy, string $fileName, string $mode): ImportLog
    {
        return ImportLog::query()->create([
            'entity' => $entity,
            'performed_by' => $performedBy?->id,
            'file_name' => $fileName,
            'mode' => $mode,
            'dry_run' => false,
            'status' => ImportLogStatus::Queued,
        ]);
    }
}
