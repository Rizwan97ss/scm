<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'invoice_id', 'student_id', 'payment_number', 'amount', 'method', 'gateway', 'reference_number', 'paid_at', 'notes', 'received_by'])]
class Payment extends Model
{
    use BelongsToSchool, HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'method' => PaymentMethod::class,
            'paid_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Mirrors Invoice::scopeVisibleTo() — a payment is only ever viewed through its own invoice's visibility rules. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            return $query->whereHas('student', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($user->hasRole('Parent')) {
            return $query->whereHas('student.guardians', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['amount', 'method', 'paid_at'])->dontLogEmptyChanges();
    }
}
