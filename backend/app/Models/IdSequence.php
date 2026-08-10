<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_id', 'key', 'last_value'])]
class IdSequence extends Model
{
    use BelongsToSchool, HasFactory;
}
