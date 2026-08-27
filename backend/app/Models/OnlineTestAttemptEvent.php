<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only integrity log for one attempt — tab_hidden/window_blur/
 * fullscreen_exit, each row timestamped and never overwritten (contrast
 * OnlineTestAnswer, which upserts to the latest selection). This is what
 * a teacher/invigilator reviews to see exactly what happened and when,
 * not just that the attempt ended early.
 */
#[Fillable(['attempt_id', 'event_type'])]
class OnlineTestAttemptEvent extends Model
{
    use HasFactory;

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OnlineTestAttempt::class, 'attempt_id');
    }
}
