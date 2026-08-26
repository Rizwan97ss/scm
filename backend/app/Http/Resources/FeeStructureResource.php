<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeeStructure */
class FeeStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'grade_level' => $this->whenLoaded('gradeLevel', fn () => $this->gradeLevel ? ['id' => $this->gradeLevel->id, 'name' => $this->gradeLevel->name] : null),
            'fee_category' => $this->whenLoaded('feeCategory', fn () => ['id' => $this->feeCategory->id, 'name' => $this->feeCategory->name]),
            'name' => $this->name,
            'amount' => $this->amount,
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'due_day_of_month' => $this->due_day_of_month,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
