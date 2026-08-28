<?php

namespace App\Enums;

enum LeavePaymentType: string
{
    case WithPay = 'with_pay';
    case WithoutPay = 'without_pay';

    public function label(): string
    {
        return match ($this) {
            self::WithPay => 'Leave With Pay',
            self::WithoutPay => 'Leave W/O Pay',
        };
    }

    public function formLabel(): string
    {
        return match ($this) {
            self::WithPay => 'LEAVE WITH PAY',
            self::WithoutPay => 'LEAVE W/O PAY',
        };
    }
}
