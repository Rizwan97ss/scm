<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['school_id', 'student_id', 'certificate_template_id', 'certificate_number', 'verification_token', 'issued_date', 'issued_by', 'content'])]
class Certificate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
        ];
    }

    /**
     * Every row gets a verification_token, not just ones created through
     * CertificateService::issue() — same "always set, regardless of call
     * site" reasoning as User::uuid's own creating hook. A scannable QR
     * code must never point at a row that happens to have none because it
     * was seeded or created some other way.
     */
    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            $certificate->verification_token ??= (string) Str::uuid();
        });
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
