<?php

namespace App\Events;

use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever an AppNotification is created, so the in-app bell
 * (NotificationBell.tsx) updates live instead of relying on its 60s poll.
 * ShouldBroadcastNow, not ShouldBroadcast -- this codebase has no queue
 * worker running (see AnnouncementService's own docblock), so a queued
 * broadcast would silently never actually go out.
 *
 * $schoolId is passed explicitly (not read from tenant() inside
 * broadcastOn()) because broadcastOn() can run later than dispatch — safer
 * to capture the tenant this notification actually belongs to at the
 * moment it's created than to assume tenancy is still the same by the time
 * the channel name is built.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public readonly AppNotification $notification,
        public readonly int $schoolId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->schoolId}.user.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return (new AppNotificationResource($this->notification))->resolve();
    }
}
