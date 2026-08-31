<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\GuardianImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\GuardiansImport;
use App\Models\Guardian;
use App\Support\ApiResponse;
use App\Support\ImportLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuardianImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Guardian::class);

        return (new GuardianImportTemplateExport)->download('guardian-import-template.xlsx');
    }

    public function __invoke(Request $request, ImportLogger $logger): JsonResponse
    {
        $this->authorize('import', Guardian::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'mode' => ['nullable', Rule::in(GuardiansImport::MODES)],
        ]);

        $dryRun = $request->boolean('dry_run');
        $mode = $request->string('mode', GuardiansImport::MODE_CREATE)->toString();

        $import = new GuardiansImport($dryRun, $mode);
        Excel::import($import, $request->file('file'));

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        $logger->log(
            entity: 'guardian',
            performedBy: $request->user(),
            fileName: $request->file('file')->getClientOriginalName(),
            mode: $mode,
            dryRun: $dryRun,
            createdCount: $import->importedCount(),
            updatedCount: $import->updatedCount(),
            failedCount: $failures->count(),
        );

        $verb = $dryRun ? 'would be' : 'were';
        $summary = "{$import->importedCount()} guardian link(s) {$verb} attached";
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
}
