<?php

namespace App\Enums;

enum LeaveBalanceAdjustmentType: string
{
    case InitialEntitlement = 'initial_entitlement';
    case ManualAddition = 'manual_addition';
    case ManualDeduction = 'manual_deduction';
    case EntitlementCorrection = 'entitlement_correction';
    case ApprovedLeaveDeduction = 'approved_leave_deduction';
    case LeaveReversal = 'leave_reversal';

    public function label(): string
    {
        return match ($this) {
            self::InitialEntitlement => 'Initial Entitlement',
            self::ManualAddition => 'Manual Addition',
            self::ManualDeduction => 'Manual Deduction',
            self::EntitlementCorrection => 'Entitlement Correction',
            self::ApprovedLeaveDeduction => 'Approved Leave Deduction',
            self::LeaveReversal => 'Leave Reversal',
        };
    }

    public static function manualTypes(): array
    {
        return [
            self::ManualAddition,
            self::ManualDeduction,
            self::EntitlementCorrection,
        ];
    }
}
