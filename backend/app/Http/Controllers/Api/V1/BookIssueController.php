<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\IssueBookRequest;
use App\Http\Resources\BookIssueResource;
use App\Models\Book;
use App\Models\BookIssue;
use App\Services\LibraryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BookIssueController extends Controller
{
    private const WITH = ['book', 'student', 'user', 'issuedBy'];

    public function __construct(private readonly LibraryService $library) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BookIssue::class);

        $paginator = QueryBuilder::for(BookIssue::query()->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('book_id'), AllowedFilter::exact('student_id'), AllowedFilter::exact('user_id'), AllowedFilter::exact('status'))
            ->allowedSorts('due_date', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(BookIssueResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(IssueBookRequest $request, Book $book): JsonResponse
    {
        $this->authorize('create', BookIssue::class);

        $issue = $this->library->issue($book, $request->validated(), $request->user());

        return ApiResponse::created(new BookIssueResource($issue->load(self::WITH)));
    }

    public function returnBook(int $id): JsonResponse
    {
        $bookIssue = BookIssue::query()->findOrFail($id);

        $this->authorize('update', $bookIssue);

        $updated = $this->library->returnBook($bookIssue);

        return ApiResponse::success(new BookIssueResource($updated->load(self::WITH)));
    }
}
