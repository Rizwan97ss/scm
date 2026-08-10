<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case BankTransfer = 'bank_transfer';
    case Upi = 'upi';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::BankTransfer => 'Bank transfer',
            self::Upi => 'UPI',
            self::Card => 'Card',
            self::Other => 'Other',
        };
    }
}
