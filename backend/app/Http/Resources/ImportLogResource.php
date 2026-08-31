<?php

namespace App\Http\Resources;

use App\Enums\ImportLogStatus;
use App\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ImportLog */
class ImportLogResource extends JsonResource
{
    /** Entities ImportUndoService knows how to reverse — kept in sync with ImportUndoService::DEPENDENT_CHECKS' keys, expressed here as the same entity labels LookupImportController subclasses return so the frontend doesn't need its own copy of the list. */
    private const UNDOABLE_ENTITIES = ['department', 'grade level', 'room', 'subject', 'section'];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity' => $this->entity,
            'performed_by' => $this->whenLoaded('performedBy', fn () => $this->performedBy ? ['id' => $this->performedBy->id, 'full_name' => $this->performedBy->full_name] : null),
            'file_name' => $this->file_name,
            'mode' => $this->mode,
            'dry_run' => $this->dry_run,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'created_count' => $this->created_count,
            'updated_count' => $this->updated_count,
            'failed_count' => $this->failed_count,
            'failures' => $this->failures ?? [],
            'warnings' => $this->warnings ?? [],
            'undone_at' => $this->undone_at?->toIso8601String(),
            'can_undo' => $this->status === ImportLogStatus::Completed
                && ! $this->dry_run
                && ! $this->undone_at
                && $this->created_count > 0
                && in_array($this->entity, self::UNDOABLE_ENTITIES, true),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
