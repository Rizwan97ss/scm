<?php

namespace App\Models;

use App\Enums\ImportLogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['entity', 'performed_by', 'file_name', 'mode', 'dry_run', 'status', 'created_count', 'updated_count', 'failed_count', 'undone_at', 'failure_reason', 'failures', 'warnings'])]
class ImportLog extends Model
{
    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'undone_at' => 'datetime',
            'status' => ImportLogStatus::class,
            'failures' => 'array',
            'warnings' => 'array',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** The specific records this import created — see ImportUndoService. Never includes updated records (see SimpleLookupImport::createdModels()). */
    public function items(): HasMany
    {
        return $this->hasMany(ImportLogItem::class);
    }
}
