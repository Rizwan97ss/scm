<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-level subscription tier — deliberately NOT school-scoped
 * (no BelongsToSchool): every school reads from the same shared set of
 * plans, they don't own or edit their own copy of one.
 */
#[Fillable([
    'key', 'name', 'description', 'stripe_product_id', 'stripe_price_id',
    'price_cents', 'currency', 'trial_days', 'max_students', 'max_staff',
    'feature_flags', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'feature_flags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
