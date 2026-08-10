<?php

namespace App\Models;

use App\Enums\BookIssueStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'book_id', 'student_id', 'user_id', 'issue_date', 'due_date', 'return_date', 'fine_amount', 'status', 'issued_by'])]
class BookIssue extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'fine_amount' => 'float',
            'status' => BookIssueStatus::class,
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getBorrowerNameAttribute(): ?string
    {
        return $this->student?->full_name ?? $this->user?->full_name;
    }
}
