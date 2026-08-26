<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'student_id', 'certificate_template_id', 'certificate_number', 'issued_date', 'issued_by', 'content'])]
class Certificate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Same shape as Invoice::scopeVisibleTo() — a Student sees only their own, a Parent only their linked children's. */
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
}
