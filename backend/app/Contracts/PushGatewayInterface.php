<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Seam between "an announcement was sent via push" and how the notification
 * was actually delivered to a device. `LogPushGateway` is the only
 * implementation Phase 11 ships — no device-token registration exists yet
 * (that's its own future piece of work), and a real provider (FCM, APNs, ...)
 * is a deliberate future integration, same reasoning as SmsGatewayInterface.
 */
interface PushGatewayInterface
{
    public function send(User $user, string $title, string $body): void;
}
