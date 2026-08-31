<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per record an import actually created (never updated — see
 * SimpleLookupImport::createdModels()) — what ImportUndoService walks to
 * find what a given ImportLog is allowed to undo.
 */
#[Fillable(['import_log_id', 'model_type', 'model_id'])]
class ImportLogItem extends Model
{
    public function importLog(): BelongsTo
    {
        return $this->belongsTo(ImportLog::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }
}
