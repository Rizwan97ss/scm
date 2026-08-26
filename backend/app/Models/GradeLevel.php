<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'name', 'code', 'sequence'])]
class GradeLevel extends Model
{
    use HasFactory, SoftDeletes;

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
