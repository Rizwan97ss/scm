<?php

use App\Services\Communication\LogPushGateway;
use App\Services\Communication\LogSmsGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | SMS / Push Gateways
    |--------------------------------------------------------------------------
    |
    | Which App\Contracts\SmsGatewayInterface / PushGatewayInterface
    | implementation is bound in the container. 'log' (writes to the
    | Laravel log, no external delivery) is the only implementation
    | Phase 11 ships — see those interfaces' docblocks for why a real
    | provider is a deliberate future integration, not built here.
    | Adding one later is a matter of registering it below and changing
    | the relevant default — no caller of either interface changes.
    |
    */

    'sms_default' => env('SMS_GATEWAY', 'log'),
    'sms_gateways' => [
        'log' => LogSmsGateway::class,
    ],

    'push_default' => env('PUSH_GATEWAY', 'log'),
    'push_gateways' => [
        'log' => LogPushGateway::class,
    ],

];
