<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_id', 'name', 'start_time', 'end_time', 'sequence', 'is_break'])]
class TimetablePeriod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
        ];
    }
}
