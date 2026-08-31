<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\SubjectImportTemplateExport;
use App\Imports\SubjectsImport;
use App\Models\Subject;

class SubjectImportController extends LookupImportController
{
    protected function modelClass(): string
    {
        return Subject::class;
    }

    protected function importClass(): string
    {
        return SubjectsImport::class;
    }

    protected function templateExport(): object
    {
        return new SubjectImportTemplateExport;
    }

    protected function templateFilename(): string
    {
        return 'subject-import-template.xlsx';
    }

    protected function entityLabel(): string
    {
        return 'subject';
    }
}
