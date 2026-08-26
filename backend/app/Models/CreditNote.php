<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'invoice_id', 'credit_note_number', 'amount', 'reason', 'issued_by', 'issued_at'])]
class CreditNote extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'issued_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['amount', 'reason'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
