<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform-level subscription tier — deliberately NOT school-scoped
 * (no BelongsToSchool): every school reads from the same shared set of
 * plans, they don't own or edit their own copy of one. CentralConnection
 * pins it to the landlord connection regardless of which tenant is
 * currently active — without it, e.g. tenant()->loadMissing('plan') from
 * inside a tenant-zone route (BillingController) would silently try to
 * query a `plans` table in the TENANT's own database, which doesn't exist
 * there (this table's migration lives in database/migrations/, not
 * database/migrations/tenant/) — the same class of bug School::class
 * itself already guards against.
 */
#[Fillable([
    'key', 'name', 'description', 'stripe_product_id', 'stripe_price_id',
    'price_cents', 'currency', 'trial_days', 'max_students', 'max_staff',
    'feature_flags', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    use HasFactory, CentralConnection;

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
