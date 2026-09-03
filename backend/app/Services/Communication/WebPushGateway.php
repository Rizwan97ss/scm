<?php

namespace App\Services\Communication;

use App\Contracts\PushGatewayInterface;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/**
 * Real PushGatewayInterface binding via the browser Push API (VAPID Web
 * Push, RFC 8030) — no external account needed, unlike Twilio, so this is
 * the actual default (see config/communication.php). Sends to every device
 * the user has subscribed from (see PushSubscription), pruning any
 * subscription the browser reports as expired/gone so it isn't retried
 * forever.
 */
class WebPushGateway implements PushGatewayInterface
{
    /**
     * $client is normally left null — the container resolves this class
     * with no constructor args, so production always builds a real WebPush
     * client below. Tests inject a mock instead, since WebPush talks
     * directly to a PSR-18 HTTP client (Guzzle), not Laravel's Http facade,
     * so Http::fake() can't intercept it.
     */
    public function __construct(private readonly ?WebPush $client = null) {}

    public function send(User $user, string $title, string $body): void
    {
        $publicKey = config('communication.vapid.public_key');
        $privateKey = config('communication.vapid.private_key');

        if (! $publicKey || ! $privateKey) {
            throw new RuntimeException('Web Push gateway is selected but VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY are not configured.');
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = $this->client ?? new WebPush([
            'VAPID' => [
                'subject' => config('communication.vapid.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                $user->pushSubscriptions()->where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
