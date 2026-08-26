<?php

namespace App\Services\Communication;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Default SmsGatewayInterface binding — writes to the log instead of
 * calling a real SMS provider. See SmsGatewayInterface's docblock for why
 * no real provider is wired up yet.
 */
class LogSmsGateway implements SmsGatewayInterface
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS (log gateway, not actually delivered)', ['to' => $to, 'message' => $message]);
    }
}
