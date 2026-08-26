<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\StudentsExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $this->authorize('export', Student::class);

        $query = Student::query()
            ->visibleTo($request->user())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('current_section_id'), fn ($q) => $q->where('current_section_id', $request->integer('current_section_id')));

        return (new StudentsExport($query))->download('students.xlsx');
    }
}
