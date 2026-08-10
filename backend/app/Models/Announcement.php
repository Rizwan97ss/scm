<?php

namespace App\Models;

use App\Enums\Audience;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'title', 'body', 'audience', 'channels', 'recipient_count', 'sent_by', 'sent_at'])]
class Announcement extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'audience' => Audience::class,
            'channels' => 'array',
            'recipient_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
