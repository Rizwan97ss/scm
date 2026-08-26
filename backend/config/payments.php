<?php

use App\Services\Payments\ManualPaymentGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Which App\Contracts\PaymentGatewayInterface implementation is bound in
    | the container. 'manual' (cash/cheque/bank-transfer/UPI/card recorded
    | by staff, no external charge) is the only implementation Phase 8 ships.
    | Adding a real online gateway later is a matter of registering it below
    | and changing this default — no caller of the interface changes.
    |
    */

    'default' => env('PAYMENT_GATEWAY', 'manual'),

    'gateways' => [
        'manual' => ManualPaymentGateway::class,
    ],

];
