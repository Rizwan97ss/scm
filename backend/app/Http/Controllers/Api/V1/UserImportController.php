<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\UserImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use App\Support\ImportLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        $this->authorize('import', User::class);

        return (new UserImportTemplateExport)->download('staff-import-template.xlsx');
    }

    public function __invoke(Request $request, UserPolicy $policy, ImportLogger $logger): JsonResponse
    {
        $this->authorize('import', User::class);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $dryRun = $request->boolean('dry_run');

        $import = new UsersImport($request->user(), $policy, $dryRun);
        Excel::import($import, $request->file('file'));

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        $logger->log(
            entity: 'staff',
            performedBy: $request->user(),
            fileName: $request->file('file')->getClientOriginalName(),
            mode: 'create',
            dryRun: $dryRun,
            createdCount: $import->importedCount(),
            updatedCount: 0,
            failedCount: $failures->count(),
        );

        $verb = $dryRun ? 'would be imported' : 'imported';

        return ApiResponse::success([
            'imported_count' => $import->importedCount(),
            'failed_count' => $failures->count(),
            'failures' => $failures,
            'dry_run' => $dryRun,
        ], "{$import->importedCount()} staff member(s) {$verb}.".($failures->count() ? " {$failures->count()} row(s) failed." : ''));
    }
}
