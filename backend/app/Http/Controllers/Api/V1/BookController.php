<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Library\StoreBookRequest;
use App\Http\Requests\Library\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookController extends CrudController
{
    protected array $allowedFilters = ['title', 'author', 'category', 'is_active'];

    protected array $allowedSorts = ['title', 'created_at'];

    protected string $defaultSort = 'title';

    protected function modelClass(): string
    {
        return Book::class;
    }

    protected function resourceClass(): string
    {
        return BookResource::class;
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $this->authorize('create', Book::class);

        $book = Book::query()->create([
            ...$request->validated(),
            // A new book's full stock starts fully available — there's no
            // history of issues yet for it to differ from total_copies.
            'available_copies' => $request->validated('total_copies'),
        ]);

        return ApiResponse::created(new BookResource($book));
    }

    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $data = $request->validated();

        // Changing total_copies shifts available_copies by the same delta
        // rather than resetting it — copies currently on loan must stay
        // accounted for. Never let available_copies go negative (e.g.
        // shrinking total_copies below how many are currently checked out).
        if (array_key_exists('total_copies', $data)) {
            $delta = $data['total_copies'] - $book->total_copies;
            $data['available_copies'] = max(0, $book->available_copies + $delta);
        }

        $book->update($data);

        return ApiResponse::success(new BookResource($book));
    }
}
