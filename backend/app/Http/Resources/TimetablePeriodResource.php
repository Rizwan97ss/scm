<?php

namespace App\Http\Resources;

use App\Models\TimetablePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimetablePeriod */
class TimetablePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'sequence' => $this->sequence,
            'is_break' => $this->is_break,
        ];
    }
}
