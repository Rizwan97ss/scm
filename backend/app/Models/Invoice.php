<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'student_id', 'academic_year_id', 'invoice_number', 'issue_date', 'due_date', 'status', 'subtotal', 'discount_total', 'total', 'amount_paid', 'credit_total', 'notes', 'created_by'])]
class Invoice extends Model
{
    use BelongsToSchool, HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'subtotal' => 'float',
            'discount_total' => 'float',
            'total' => 'float',
            'amount_paid' => 'float',
            'credit_total' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    protected function balance(): Attribute
    {
        return Attribute::get(fn () => round($this->total - $this->amount_paid - $this->credit_total, 2));
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => in_array($this->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)
            && $this->due_date !== null
            && $this->due_date->isPast());
    }

    /**
     * Same shape as Homework::scopeVisibleTo()'s Student/Parent branches —
     * a Student sees only their own invoices, a Parent only their linked
     * children's. Staff roles that hold invoices.view (School Admin,
     * Principal, Management, Accountant) are school-wide, no row narrowing.
     */
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
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'amount_paid', 'due_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
