<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\StudentImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\PlanLimitService;
use App\Services\StudentEnrollmentService;
use App\Services\StudentIdGeneratorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Student::class);

        return (new StudentImportTemplateExport)->download('student-import-template.xlsx');
    }

    public function __invoke(Request $request, StudentIdGeneratorService $idGenerator, StudentEnrollmentService $enrollment, PlanLimitService $planLimits): JsonResponse
    {
        $this->authorize('import', Student::class);

        // Checked once against current usage, not per row — a hard "you're
        // at your limit, upgrade to import more" gate rather than a precise
        // per-row cutoff mid-import (see PlanLimitService's docblock).
        // TODO(tenancy): Super Admin detection needs PlatformUser (Sub-phase E) -- do not guess at a replacement.
        if ($request->user()->school_id !== null) {
            $planLimits->assertCanAddStudent($request->user()->school);
        }

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $academicYear = AcademicYear::query()->where('is_current', true)->first();

        if (! $academicYear) {
            throw ValidationException::withMessages(['file' => 'No current academic year is set for this school yet.']);
        }

        $import = new StudentsImport(
            $request->user()->school,
            $academicYear,
            $request->user(),
            $idGenerator,
            $enrollment,
        );

        Excel::import($import, $request->file('file'));

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        return ApiResponse::success([
            'imported_count' => $import->importedCount(),
            'failed_count' => $failures->count(),
            'failures' => $failures,
        ], "{$import->importedCount()} student(s) imported.".($failures->count() ? " {$failures->count()} row(s) failed." : ''));
    }
}
