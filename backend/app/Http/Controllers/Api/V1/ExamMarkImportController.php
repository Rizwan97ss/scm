<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ExamMarkImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ExamMarksImport;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Services\ExamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExamMarkImportController extends Controller
{
    public function __construct(private readonly ExamService $exams) {}

    public function template(): BinaryFileResponse
    {
        $this->authorize('import', ExamMark::class);

        return (new ExamMarkImportTemplateExport)->download('exam-marks-import-template.xlsx');
    }

    public function __invoke(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $this->authorize('import', ExamMark::class);
        $this->assertSubjectMarkable($request, $examSubject);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new ExamMarksImport($examSubject);
        Excel::import($import, $request->file('file'));

        $records = $this->exams->markBulk($examSubject, $import->entries(), $request->user());

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ]);

        return ApiResponse::success([
            'imported_count' => $records->count(),
            'failed_count' => $failures->count(),
            'failures' => $failures,
        ], "{$records->count()} mark(s) imported.".($failures->count() ? " {$failures->count()} row(s) failed." : ''));
    }

    /** Mirrors ExamMarkController::assertSubjectMarkable() exactly — importing a batch of marks needs the same authorization as entering them one at a time. */
    private function assertSubjectMarkable(Request $request, ExamSubject $examSubject): void
    {
        $user = $request->user();

        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($examSubject->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }
}
