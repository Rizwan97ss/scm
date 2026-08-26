<?php

namespace App\Contracts;

/**
 * Seam between "an announcement was sent via SMS" and how the text message
 * was actually delivered. `LogSmsGateway` (writes to the Laravel log,
 * no external charge) is the only implementation Phase 11 ships — a real
 * provider (Twilio, Vonage, ...) is a deliberate future integration, not
 * built here, following the exact same reasoning as PaymentGatewayInterface:
 * the first concrete non-stub implementation is a "pause and confirm with
 * the user" checkpoint (a real provider needs real credentials and a real
 * per-message cost), not something to guess at. Swapping one in later is a
 * container-binding change in AppServiceProvider (see config/communication.php),
 * not a rewrite of anything that calls this interface.
 */
interface SmsGatewayInterface
{
    public function send(string $to, string $message): void;
}
