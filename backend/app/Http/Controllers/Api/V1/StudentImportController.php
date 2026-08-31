<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\StudentImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Jobs\ProcessStudentImportJob;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\PlanLimitService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use App\Support\ApiResponse;
use App\Support\ImportLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    /**
     * Files at or under this are processed inline, exactly as before — the
     * common case (a few dozen/hundred admissions) shouldn't pay a polling
     * round-trip for no reason. Above it, a real commit is handed to the
     * queue worker instead (see ProcessStudentImportJob and
     * docs/deployment.md §5) so a large roster upload can't block the
     * request past PHP-FPM's timeout or tie up the browser tab. A dry-run
     * preview is never queued, regardless of size — it writes nothing, and
     * the whole point of a preview is seeing the result immediately.
     */
    private const ASYNC_THRESHOLD_BYTES = 262144; // 256 KB

    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Student::class);

        return (new StudentImportTemplateExport)->download('student-import-template.xlsx');
    }

    public function __invoke(Request $request, StudentIdGeneratorService $idGenerator, StudentEnrollmentService $enrollment, PlanLimitService $planLimits, ImportLogger $logger): JsonResponse
    {
        $this->authorize('import', Student::class);

        $dryRun = $request->boolean('dry_run');

        // Checked once against current usage, not per row — a hard "you're
        // at your limit, upgrade to import more" gate rather than a precise
        // per-row cutoff mid-import (see PlanLimitService's docblock). Never
        // checked for a dry run, which writes nothing regardless of plan
        // headroom. Super Admin (PlatformUser) can't reach this
        // tenant-scoped route at all, so no bypass is needed here.
        if (! $dryRun) {
            $planLimits->assertCanAddStudent(tenant());
        }

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $academicYear = AcademicYear::query()->where('is_current', true)->first();

        if (! $academicYear) {
            throw ValidationException::withMessages(['file' => 'No current academic year is set for this school yet.']);
        }

        $file = $request->file('file');

        if (! $dryRun && $file->getSize() > self::ASYNC_THRESHOLD_BYTES) {
            return $this->queueImport($request, $file, $academicYear, $logger);
        }

        $import = new StudentsImport(
            tenant(),
            $academicYear,
            $request->user(),
            $idGenerator,
            $enrollment,
            $dryRun,
        );

        Excel::import($import, $file);

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        $warnings = $import->duplicateWarnings();

        $logger->log(
            entity: 'student',
            performedBy: $request->user(),
            fileName: $file->getClientOriginalName(),
            mode: 'create',
            dryRun: $dryRun,
            createdCount: $import->importedCount(),
            updatedCount: 0,
            failedCount: $failures->count(),
            failures: $failures->all(),
            warnings: $warnings,
        );

        $verb = $dryRun ? 'would be imported' : 'imported';

        return ApiResponse::success([
            'imported_count' => $import->importedCount(),
            'failed_count' => $failures->count(),
            'failures' => $failures,
            'warnings' => $warnings,
            'dry_run' => $dryRun,
        ], "{$import->importedCount()} student(s) {$verb}."
            .($failures->count() ? " {$failures->count()} row(s) failed." : '')
            .(count($warnings) ? ' '.count($warnings).' possible duplicate(s) to review.' : ''));
    }

    private function queueImport(Request $request, UploadedFile $file, AcademicYear $academicYear, ImportLogger $logger): JsonResponse
    {
        $storedPath = $file->store('imports', 'local');

        $log = $logger->logQueued(
            entity: 'student',
            performedBy: $request->user(),
            fileName: $file->getClientOriginalName(),
            mode: 'create',
        );

        ProcessStudentImportJob::dispatch($log->id, $storedPath, $academicYear->id, $request->user()->id);

        return ApiResponse::success([
            'queued' => true,
            'import_log_id' => $log->id,
        ], "This file is large enough that it's being processed in the background — check Import Logs for the result.", 202);
    }
}
