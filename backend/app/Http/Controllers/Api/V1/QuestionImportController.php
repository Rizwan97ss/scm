<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\QuestionImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\McqQuestionsImport;
use App\Models\Question;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Question::class);

        return (new QuestionImportTemplateExport)->download('mcq-question-import-template.xlsx');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('import', Question::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $subject = Subject::query()->findOrFail($request->integer('subject_id'));

        $import = new McqQuestionsImport($subject, $request->user());

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
        ], "{$import->importedCount()} question(s) imported.".($failures->count() ? " {$failures->count()} row(s) failed." : ''));
    }
}
