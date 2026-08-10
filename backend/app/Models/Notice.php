<?php

namespace App\Models;

use App\Enums\Audience;
use App\Enums\NoticeType;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'title', 'body', 'type', 'audience', 'event_date', 'start_time', 'end_time', 'location', 'is_published', 'published_at', 'expires_at', 'created_by'])]
class Notice extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => NoticeType::class,
            'audience' => Audience::class,
            'event_date' => 'date',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Anyone holding notice-board.view sees everything, including drafts —
     * that's the "manage the board" side. Everyone else only sees published,
     * non-expired notices whose audience includes them.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('notice-board.view')) {
            return $query;
        }

        $audiences = ['all'];
        if ($user->hasRole('Student')) {
            $audiences[] = 'students';
        } elseif ($user->hasRole('Parent')) {
            $audiences[] = 'parents';
        } elseif ($user->isStaff()) {
            $audiences[] = 'staff';
        }

        return $query
            ->where('is_published', true)
            ->whereIn('audience', $audiences)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()));
    }
}
