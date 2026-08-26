<?php

namespace App\Services\Communication;

use App\Contracts\PushGatewayInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Default PushGatewayInterface binding — writes to the log instead of
 * calling a real push provider. See PushGatewayInterface's docblock for why
 * no real provider (or device-token registration) is wired up yet.
 */
class LogPushGateway implements PushGatewayInterface
{
    public function send(User $user, string $title, string $body): void
    {
        Log::info('Push notification (log gateway, not actually delivered)', ['user_id' => $user->id, 'title' => $title, 'body' => $body]);
    }
}
