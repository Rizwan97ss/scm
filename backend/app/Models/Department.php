<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'name', 'code', 'description'])]
class Department extends Model
{
    use HasFactory, SoftDeletes;

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
