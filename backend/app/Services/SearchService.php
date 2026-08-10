<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cross-entity global search for the Topbar search bar. Reachable by any
 * authenticated user (SearchController has no permission gate) — each
 * category is only queried when the caller holds that module's own .view
 * permission, so a category simply doesn't appear rather than 403ing the
 * whole search, same "compute only what you're allowed to see" shape the
 * Phase 12 reports use.
 */
class SearchService
{
    private const PER_CATEGORY_LIMIT = 5;

    /**
     * @return array<string, array<int, array{id: int, label: string, sublabel: ?string}>>
     */
    public function search(string $query, User $user): array
    {
        $results = [];

        if ($user->can('students.view')) {
            $results['students'] = Student::query()
                ->where(fn ($q) => $this->matchesName($q, $query)->orWhere('admission_number', 'like', "%{$query}%"))
                ->limit(self::PER_CATEGORY_LIMIT)
                ->get()
                ->map(fn (Student $student) => ['id' => $student->id, 'label' => $student->full_name, 'sublabel' => $student->admission_number])
                ->all();
        }

        if ($user->can('guardians.view')) {
            $results['guardians'] = Guardian::query()
                ->where(fn ($q) => $this->matchesName($q, $query)->orWhere('phone', 'like', "%{$query}%"))
                ->limit(self::PER_CATEGORY_LIMIT)
                ->get()
                ->map(fn (Guardian $guardian) => ['id' => $guardian->id, 'label' => $guardian->full_name, 'sublabel' => $guardian->phone])
                ->all();
        }

        if ($user->can('users.view')) {
            $results['staff'] = User::query()
                ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['Student', 'Parent']))
                ->where(fn ($q) => $this->matchesName($q, $query)
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('employee_id', 'like', "%{$query}%"))
                ->limit(self::PER_CATEGORY_LIMIT)
                ->get()
                ->map(fn (User $staff) => ['id' => $staff->id, 'label' => $staff->full_name, 'sublabel' => $staff->email])
                ->all();
        }

        if ($user->can('library.view')) {
            $results['books'] = Book::query()
                ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")
                    ->orWhere('isbn', 'like', "%{$query}%"))
                ->limit(self::PER_CATEGORY_LIMIT)
                ->get()
                ->map(fn (Book $book) => ['id' => $book->id, 'label' => $book->title, 'sublabel' => $book->author])
                ->all();
        }

        if ($user->can('invoices.view')) {
            $results['invoices'] = Invoice::query()
                ->visibleTo($user)
                ->where('invoice_number', 'like', "%{$query}%")
                ->with('student')
                ->limit(self::PER_CATEGORY_LIMIT)
                ->get()
                ->map(fn (Invoice $invoice) => ['id' => $invoice->id, 'label' => $invoice->invoice_number, 'sublabel' => $invoice->student?->full_name])
                ->all();
        }

        return $results;
    }

    /**
     * Matches a "first_name"/"last_name" pair against a query that may be a
     * single token ("Sam") or a full "First Last" name — a plain
     * `first_name LIKE '%Sam Student%'` never matches either column alone,
     * since neither one contains the other's word. Every word in the query
     * must match first_name OR last_name (not necessarily the same one),
     * so "Sam Student" only matches a row where one word hits each column.
     */
    private function matchesName(Builder $query, string $search): Builder
    {
        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [$search];

        return $query->where(function (Builder $outer) use ($words) {
            foreach ($words as $word) {
                $outer->where(fn (Builder $inner) => $inner->where('first_name', 'like', "%{$word}%")->orWhere('last_name', 'like', "%{$word}%"));
            }
        });
    }
}
