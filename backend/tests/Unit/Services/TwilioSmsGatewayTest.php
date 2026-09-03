<?php

namespace Tests\Unit\Services;

use App\Services\Communication\TwilioSmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TwilioSmsGatewayTest extends TestCase
{
    private function configureCredentials(): void
    {
        config([
            'communication.twilio.sid' => 'AC_test_sid',
            'communication.twilio.auth_token' => 'test_token',
            'communication.twilio.from_number' => '+15005550006',
        ]);
    }

    public function test_send_posts_the_message_to_twilios_rest_api_with_basic_auth(): void
    {
        $this->configureCredentials();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

        (new TwilioSmsGateway)->send('+15551234567', 'School closed Friday.');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test_sid/Messages.json'
                && $request['To'] === '+15551234567'
                && $request['From'] === '+15005550006'
                && $request['Body'] === 'School closed Friday.'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_send_throws_when_twilio_responds_with_a_failure_status(): void
    {
        $this->configureCredentials();
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Invalid number'], 400)]);

        $this->expectException(RuntimeException::class);

        (new TwilioSmsGateway)->send('+15551234567', 'Test');
    }

    public function test_send_throws_when_credentials_are_not_configured(): void
    {
        config(['communication.twilio.sid' => null, 'communication.twilio.auth_token' => null, 'communication.twilio.from_number' => null]);

        $this->expectException(RuntimeException::class);

        (new TwilioSmsGateway)->send('+15551234567', 'Test');
    }
}
