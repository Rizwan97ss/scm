<?php

namespace App\Http\Resources;

use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guardian */
class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'occupation' => $this->occupation,
            'national_id' => $this->national_id,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'has_portal_access' => $this->user_id !== null,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn ($student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'relationship_type' => $student->pivot->relationship_type?->value,
                'is_primary' => $student->pivot->is_primary,
            ])),
        ];
    }
}
