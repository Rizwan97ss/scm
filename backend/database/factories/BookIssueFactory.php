<?php

namespace Database\Factories;

use App\Enums\BookIssueStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookIssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'student_id' => null,
            'user_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeeks(2)->toDateString(),
            'return_date' => null,
            'fine_amount' => 0,
            'status' => BookIssueStatus::Issued,
            'issued_by' => User::factory(),
        ];
    }
}
