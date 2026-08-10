<?php

namespace App\Services;

use App\Enums\BookIssueStatus;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns the book-copy counter and fine calculation so controllers never touch
 * `available_copies`/`fine_amount` directly — same shape as InvoiceService
 * owning amount_paid/status.
 */
class LibraryService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array{student_id?: ?int, user_id?: ?int, due_date: string}  $data
     */
    public function issue(Book $book, array $data, User $issuedBy): BookIssue
    {
        abort_if($book->available_copies < 1, 422, 'No copies of this book are currently available.');

        return DB::transaction(function () use ($book, $data, $issuedBy) {
            $book->decrement('available_copies');

            return BookIssue::query()->create([
                'book_id' => $book->id,
                'student_id' => $data['student_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'issue_date' => now()->toDateString(),
                'due_date' => $data['due_date'],
                'status' => BookIssueStatus::Issued,
                'issued_by' => $issuedBy->id,
            ]);
        });
    }

    public function returnBook(BookIssue $bookIssue): BookIssue
    {
        abort_if($bookIssue->status === BookIssueStatus::Returned, 422, 'This book has already been returned.');

        return DB::transaction(function () use ($bookIssue) {
            $returnDate = now();
            $fine = $this->calculateFine($bookIssue->due_date, $returnDate);

            $bookIssue->update([
                'return_date' => $returnDate->toDateString(),
                'fine_amount' => $fine,
                'status' => BookIssueStatus::Returned,
            ]);

            $bookIssue->book()->increment('available_copies');

            return $bookIssue->refresh();
        });
    }

    private function calculateFine(Carbon $dueDate, Carbon $returnDate): float
    {
        // Clone before mutating — $dueDate is the live BookIssue model's own
        // due_date attribute, and Carbon's startOfDay() mutates in place.
        $daysLate = (int) $dueDate->copy()->startOfDay()->diffInDays($returnDate->copy()->startOfDay(), false);

        if ($daysLate <= 0) {
            return 0.0;
        }

        $perDay = (float) $this->settings->get('library.fine_per_day', 0);

        return round($daysLate * $perDay, 2);
    }
}
