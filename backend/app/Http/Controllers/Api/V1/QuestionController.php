<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Exam\StoreQuestionRequest;
use App\Http\Requests\Exam\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuestionController extends CrudController
{
    protected array $with = ['subject', 'options'];

    protected function modelClass(): string
    {
        return Question::class;
    }

    protected function resourceClass(): string
    {
        return QuestionResource::class;
    }

    /**
     * Overrides CrudController::index() — 'type' needs exact-match filtering
     * (AllowedFilter::exact()), which can't be expressed via the
     * $allowedFilters property since that's a class constant-expression
     * context and exact() is a method call, not a constant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Question::class);

        $paginator = QueryBuilder::for(Question::class)
            ->allowedFilters(AllowedFilter::exact('subject_id'), AllowedFilter::exact('type'))
            ->allowedSorts(...$this->allowedSorts)
            ->defaultSort($this->defaultSort)
            ->with($this->with)
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(QuestionResource::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    public function store(StoreQuestionRequest $request): JsonResponse
    {
        $this->authorize('create', Question::class);

        $question = DB::transaction(function () use ($request) {
            $question = Question::query()->create([
                ...$request->safe()->except('options'),
                'created_by' => $request->user()->id,
            ]);
            $this->syncOptions($question, $request->input('options', []));

            return $question;
        });

        return ApiResponse::created(new QuestionResource($question->load($this->with)));
    }

    public function update(UpdateQuestionRequest $request, Question $question): JsonResponse
    {
        $this->authorize('update', $question);

        DB::transaction(function () use ($request, $question) {
            $question->update($request->safe()->except('options'));
            $this->syncOptions($question, $request->input('options', []));
        });

        return ApiResponse::success(new QuestionResource($question->load($this->with)));
    }

    /**
     * Unlike ExamSubject's exam_subjects sync, this genuinely does replace
     * options wholesale — a question's options aren't referenced by score
     * (OnlineTestAnswer.marks_awarded/is_correct are computed once at submit
     * time and stored independently), only by selected_option_id for
     * after-the-fact review, which nulls out (FK nullOnDelete) if a question
     * already used in a submitted attempt is edited later. Acceptable for
     * now since questions are normally finalized before use; revisit with a
     * "used in a submitted attempt" guard if that assumption breaks.
     */
    private function syncOptions(Question $question, array $options): void
    {
        $question->options()->delete();

        foreach ($options as $index => $option) {
            QuestionOption::query()->create([
                'question_id' => $question->id,
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'] ?? false,
                'sequence' => $index,
            ]);
        }
    }
}
