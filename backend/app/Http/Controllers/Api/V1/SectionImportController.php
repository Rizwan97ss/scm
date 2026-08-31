<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\SectionImportTemplateExport;
use App\Imports\Concerns\SimpleLookupImport;
use App\Imports\SectionsImport;
use App\Models\AcademicYear;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SectionImportController extends LookupImportController
{
    protected function modelClass(): string
    {
        return Section::class;
    }

    protected function buildImport(Request $request, bool $dryRun, string $mode): SimpleLookupImport
    {
        $academicYear = AcademicYear::query()->where('is_current', true)->first();

        if (! $academicYear) {
            throw ValidationException::withMessages(['file' => 'No current academic year is set yet.']);
        }

        return new SectionsImport($academicYear, $dryRun, $mode);
    }

    protected function templateExport(): object
    {
        return new SectionImportTemplateExport;
    }

    protected function templateFilename(): string
    {
        return 'section-import-template.xlsx';
    }

    protected function entityLabel(): string
    {
        return 'section';
    }
}
