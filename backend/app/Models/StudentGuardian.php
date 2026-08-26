<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentGuardian extends Pivot
{
    protected $table = 'student_guardian';

    protected function casts(): array
    {
        return [
            'relationship_type' => GuardianRelationship::class,
            'is_primary' => 'boolean',
            'can_pickup' => 'boolean',
        ];
    }
}
