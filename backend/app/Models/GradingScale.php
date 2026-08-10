<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'name', 'is_default'])]
class GradingScale extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function gradeBands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_percentage');
    }

    /**
     * Null when marks fall outside every band (e.g. a scale with gaps) —
     * callers must treat that as "ungraded", not assume a match always exists.
     */
    public function resolveBand(float $percentage): ?GradeBand
    {
        return $this->gradeBands->first(
            fn (GradeBand $band) => $percentage >= $band->min_percentage && $percentage <= $band->max_percentage
        );
    }
}
