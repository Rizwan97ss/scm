<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\GradeLevelImportTemplateExport;
use App\Imports\GradeLevelsImport;
use App\Models\GradeLevel;

class GradeLevelImportController extends LookupImportController
{
    protected function modelClass(): string
    {
        return GradeLevel::class;
    }

    protected function importClass(): string
    {
        return GradeLevelsImport::class;
    }

    protected function templateExport(): object
    {
        return new GradeLevelImportTemplateExport;
    }

    protected function templateFilename(): string
    {
        return 'grade-level-import-template.xlsx';
    }

    protected function entityLabel(): string
    {
        return 'grade level';
    }
}
