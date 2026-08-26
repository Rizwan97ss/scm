<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'title', 'author', 'isbn', 'category', 'total_copies', 'available_copies', 'is_active'])]
class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'total_copies' => 'integer',
            'available_copies' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class);
    }
}
