<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\QuestionImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\McqQuestionsImport;
use App\Models\ExamSubject;
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
            'exam_subject_id' => ['nullable', 'exists:exam_subjects,id'],
        ]);

        $subject = Subject::query()->findOrFail($request->integer('subject_id'));

        $examSubject = $request->filled('exam_subject_id')
            ? ExamSubject::query()->findOrFail($request->integer('exam_subject_id'))
            : null;

        if ($examSubject) {
            $this->assertCanConfigure($request, $examSubject);
        }

        $import = new McqQuestionsImport($subject, $request->user(), $examSubject);

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

    /**
     * Same rule as OnlineTestController::assertCanConfigure() — importing
     * straight into a specific test is a form of authoring that test, so it
     * requires the same "actually teaches this subject/section" check for
     * non-admin roles that configuring the test itself already enforces.
     */
    private function assertCanConfigure(Request $request, ExamSubject $examSubject): void
    {
        $user = $request->user();
        abort_unless($user->can('online-exams.configure'), 403);

        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($examSubject->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }
}
