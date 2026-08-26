<?php

namespace App\Http\Resources;

use App\Models\AssessmentComponentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssessmentComponentType */
class AssessmentComponentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_auto_graded' => $this->is_auto_graded,
            'sequence' => $this->sequence,
            'is_active' => $this->is_active,
        ];
    }
}
