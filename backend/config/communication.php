<?php

use App\Services\Communication\LogPushGateway;
use App\Services\Communication\LogSmsGateway;
use App\Services\Communication\TwilioSmsGateway;
use App\Services\Communication\WebPushGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | SMS / Push Gateways
    |--------------------------------------------------------------------------
    |
    | Which App\Contracts\SmsGatewayInterface / PushGatewayInterface
    | implementation is bound in the container. 'log' (writes to the
    | Laravel log, no external delivery) stays the SMS default until real
    | Twilio credentials exist below — flip SMS_GATEWAY=twilio once they do.
    | Push defaults to the real 'web' (VAPID Web Push) gateway since that
    | needs no external account to activate. Adding a gateway is a matter
    | of registering it below and changing the relevant default — no
    | caller of either interface changes.
    |
    */

    'sms_default' => env('SMS_GATEWAY', 'log'),
    'sms_gateways' => [
        'log' => LogSmsGateway::class,
        'twilio' => TwilioSmsGateway::class,
    ],

    'push_default' => env('PUSH_GATEWAY', 'web'),
    'push_gateways' => [
        'log' => LogPushGateway::class,
        'web' => WebPushGateway::class,
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
    ],

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:support@academia-erp.tech'),
    ],

];
