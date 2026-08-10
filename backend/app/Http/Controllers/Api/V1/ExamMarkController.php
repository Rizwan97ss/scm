<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\MarkExamRequest;
use App\Http\Requests\Exam\UpdateExamMarkRequest;
use App\Http\Resources\ExamMarkResource;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Services\ExamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamMarkController extends Controller
{
    private const WITH = ['student', 'enteredBy'];

    public function __construct(private readonly ExamService $exams) {}

    public function index(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $this->authorize('viewAny', ExamMark::class);

        $marks = ExamMark::query()
            ->where('exam_subject_id', $examSubject->id)
            ->with(self::WITH)
            ->visibleTo($request->user())
            ->get();

        return ApiResponse::success(ExamMarkResource::collection($marks));
    }

    public function store(MarkExamRequest $request, ExamSubject $examSubject): JsonResponse
    {
        $this->authorize('enter', ExamMark::class);
        $this->assertSubjectMarkable($request, $examSubject);

        $records = $this->exams->markBulk($examSubject, $request->input('entries'), $request->user());

        return ApiResponse::success(
            ExamMarkResource::collection($records->load(self::WITH)),
            $records->count().' mark(s) saved.'
        );
    }

    public function update(UpdateExamMarkRequest $request, ExamMark $examMark): JsonResponse
    {
        $this->authorize('update', $examMark);

        $updated = $this->exams->correctMark($examMark, $request->validated(), $request->user());

        return ApiResponse::success(new ExamMarkResource($updated->load(self::WITH)));
    }

    /**
     * Only School Admin/Principal/Super Admin may enter marks for a subject
     * they don't personally teach — a Teacher or Class Teacher must be the
     * ClassSubjectTeacher assigned to this exact subject+section, even
     * though Class Teachers can *view* every subject in their section (see
     * ExamMark::scopeVisibleTo()). Overseeing a report card and grading a
     * subject are different privileges.
     */
    private function assertSubjectMarkable(Request $request, ExamSubject $examSubject): void
    {
        $user = $request->user();

        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($examSubject->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }
}
