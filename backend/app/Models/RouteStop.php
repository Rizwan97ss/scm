<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'route_id', 'name', 'sequence'])]
class RouteStop extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
