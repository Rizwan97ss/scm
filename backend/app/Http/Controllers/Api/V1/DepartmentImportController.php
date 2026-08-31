<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\DepartmentImportTemplateExport;
use App\Imports\DepartmentsImport;
use App\Models\Department;

class DepartmentImportController extends LookupImportController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function importClass(): string
    {
        return DepartmentsImport::class;
    }

    protected function templateExport(): object
    {
        return new DepartmentImportTemplateExport;
    }

    protected function templateFilename(): string
    {
        return 'department-import-template.xlsx';
    }

    protected function entityLabel(): string
    {
        return 'department';
    }
}
