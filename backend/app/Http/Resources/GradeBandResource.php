<?php

namespace App\Http\Resources;

use App\Models\GradeBand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradeBand */
class GradeBandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'min_percentage' => $this->min_percentage,
            'max_percentage' => $this->max_percentage,
            'grade_label' => $this->grade_label,
            'grade_point' => $this->grade_point,
            'remark' => $this->remark,
        ];
    }
}
