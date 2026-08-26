<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_id', 'name', 'code', 'is_auto_graded', 'sequence', 'is_active'])]
class AssessmentComponentType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_auto_graded' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
