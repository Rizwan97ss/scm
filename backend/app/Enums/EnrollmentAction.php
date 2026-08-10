<?php

namespace App\Enums;

enum EnrollmentAction: string
{
    case Admission = 'admission';
    case Promotion = 'promotion';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Withdrawal = 'withdrawal';
    case Graduation = 'graduation';
    case Reactivation = 'reactivation';

    public function label(): string
    {
        return match ($this) {
            self::Admission => 'Admission',
            self::Promotion => 'Promotion',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::Withdrawal => 'Withdrawal',
            self::Graduation => 'Graduation',
            self::Reactivation => 'Reactivation',
        };
    }

    /**
     * The resulting StudentStatus once this action is applied.
     */
    public function resultingStatus(): StudentStatus
    {
        return match ($this) {
            self::Admission, self::Promotion, self::TransferIn, self::Reactivation => StudentStatus::Active,
            self::TransferOut => StudentStatus::TransferredOut,
            self::Withdrawal => StudentStatus::Withdrawn,
            self::Graduation => StudentStatus::Graduated,
        };
    }
}
