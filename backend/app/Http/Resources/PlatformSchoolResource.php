<?php

namespace App\Http\Resources;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Platform-admin-only view of a school — distinct from SchoolResource
 * (which a School Admin can see for their own school): carries billing
 * internals (stripe_id, plan, usage vs. limits) no tenant-side consumer
 * should ever receive.
 *
 * @mixin School
 */
class PlatformSchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'stripe_id' => $this->stripe_id,
            'billing_status' => $this->billing_status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->id,
                'key' => $this->plan->key,
                'name' => $this->plan->name,
            ] : null),
            'usage' => [
                'students' => $this->studentCount(),
                'max_students' => $this->plan?->max_students,
                'staff' => $this->staffCount(),
                'max_staff' => $this->plan?->max_staff,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
