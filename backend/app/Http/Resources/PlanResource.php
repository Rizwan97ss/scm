<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately excludes stripe_product_id/stripe_price_id — this is public,
 * unauthenticated (the signup pricing page), no reason to expose Stripe
 * object ids to every visitor.
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'trial_days' => $this->trial_days,
            'max_students' => $this->max_students,
            'max_staff' => $this->max_staff,
            'feature_flags' => $this->feature_flags,
        ];
    }
}
