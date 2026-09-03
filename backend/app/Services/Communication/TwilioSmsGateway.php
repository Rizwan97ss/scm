<?php

namespace App\Services\Communication;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real SmsGatewayInterface binding via Twilio's REST API — a plain HTTP
 * POST (basic-auth'd with the Account SID/Auth Token), not the twilio/sdk
 * package, since the API surface this app needs is one endpoint. Ships
 * registered in config/communication.php but inactive (SMS_GATEWAY stays
 * 'log') until real TWILIO_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM_NUMBER values
 * are in .env — see that config file's docblock.
 *
 * Throws on any non-2xx response so the queued SendSmsJob that calls this
 * (see app/Jobs/SendSmsJob.php) actually retries instead of silently
 * swallowing a failed send.
 */
class TwilioSmsGateway implements SmsGatewayInterface
{
    public function send(string $to, string $message): void
    {
        $sid = config('communication.twilio.sid');
        $token = config('communication.twilio.auth_token');
        $from = config('communication.twilio.from_number');

        if (! $sid || ! $token || ! $from) {
            throw new RuntimeException('Twilio SMS gateway is selected but TWILIO_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM_NUMBER are not all configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Twilio SMS send failed ({$response->status()}): {$response->body()}");
        }
    }
}
