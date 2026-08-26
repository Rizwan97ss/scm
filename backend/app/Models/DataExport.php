<?php

namespace App\Models;

use App\Enums\DataExportScope;
use App\Enums\DataExportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scope', 'status', 'requested_by', 'file_path', 'failure_reason', 'expires_at'])]
class DataExport extends Model
{
    protected function casts(): array
    {
        return [
            'scope' => DataExportScope::class,
            'status' => DataExportStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
