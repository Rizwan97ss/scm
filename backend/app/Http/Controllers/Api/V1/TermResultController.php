<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Term;
use App\Services\TermResultService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TermResultController extends Controller
{
    public function __construct(private readonly TermResultService $termResults) {}

    public function show(Request $request, Term $term): JsonResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        // A term result aggregates only published exams (see TermResultService),
        // so there's no separate publish gate to check here the way
        // ExamController::reportCard() checks a single exam's is_published —
        // an unpublished exam simply doesn't contribute to the weighted average.

        return ApiResponse::success($this->termResults->termResult($term, $student));
    }

    public function pdf(Request $request, Term $term): Response
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        $data = $this->termResults->termResult($term, $student);

        $pdf = Pdf::loadView('pdf.term-result', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        $fileName = str($data['student']['full_name'].'-'.$data['term']['name'])->slug().'-term-result.pdf';

        return $pdf->download($fileName);
    }
}
