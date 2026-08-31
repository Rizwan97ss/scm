<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Imports\Concerns\SimpleLookupImport;
use App\Support\ApiResponse;
use App\Support\ImportLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shared template()/__invoke() for the plain lookup-table imports
 * (Departments, Grade Levels, Sections, Subjects, Rooms) — same rationale
 * as SimpleLookupImport: five otherwise-identical controllers duplicating
 * the same authorize/validate/dry-run/mode/response-shaping boilerplate.
 */
abstract class LookupImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        $this->authorize('import', $this->modelClass());

        /** @var Exportable $export */
        $export = $this->templateExport();

        return $export->download($this->templateFilename());
    }

    public function __invoke(Request $request, ImportLogger $logger): JsonResponse
    {
        $this->authorize('import', $this->modelClass());

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'mode' => ['nullable', Rule::in(SimpleLookupImport::MODES)],
        ]);

        $dryRun = $request->boolean('dry_run');
        $mode = $request->string('mode', SimpleLookupImport::MODE_CREATE)->toString();

        $import = $this->buildImport($request, $dryRun, $mode);

        Excel::import($import, $request->file('file'));

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        $logger->log(
            entity: $this->entityLabel(),
            performedBy: $request->user(),
            fileName: $request->file('file')->getClientOriginalName(),
            mode: $mode,
            dryRun: $dryRun,
            createdCount: $import->importedCount(),
            updatedCount: $import->updatedCount(),
            failedCount: $failures->count(),
            createdModels: $import->createdModels(),
        );

        $verb = $dryRun ? 'would be' : 'were';
        $summary = "{$import->importedCount()} {$this->entityLabel()}(s) {$verb} created";
        if ($import->updatedCount() > 0) {
            $summary .= ", {$import->updatedCount()} {$verb} updated";
        }
        $summary .= '.'.($failures->count() ? " {$failures->count()} row(s) failed." : '');

        return ApiResponse::success([
            'imported_count' => $import->importedCount(),
            'updated_count' => $import->updatedCount(),
            'failed_count' => $failures->count(),
            'failures' => $failures,
            'dry_run' => $dryRun,
            'mode' => $mode,
        ], $summary);
    }

    /**
     * Default construction: `new (importClass())($dryRun, $mode)`.
     * Overridden by a subclass whose Import needs extra context —
     * SectionsImport takes the current AcademicYear, the same "no current
     * academic year" guard StudentImportController already has, since a
     * section can't exist without one.
     */
    protected function buildImport(Request $request, bool $dryRun, string $mode): SimpleLookupImport
    {
        $importClass = $this->importClass();

        return new $importClass($dryRun, $mode);
    }

    /** The model class, for the `import` Policy gate. */
    abstract protected function modelClass(): string;

    /** @return class-string<SimpleLookupImport> */
    protected function importClass(): string
    {
        throw new \LogicException(static::class.' must implement importClass() or override buildImport() directly.');
    }

    abstract protected function templateExport(): object;

    abstract protected function templateFilename(): string;

    /** Used in the response message and the import log, e.g. "department" -> "3 department(s) imported." */
    abstract protected function entityLabel(): string;
}
