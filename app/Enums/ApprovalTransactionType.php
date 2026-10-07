<?php

namespace App\Enums;

enum ApprovalTransactionType: string
{
    case LeaveApplication = 'leave_application';
    case Pardon = 'pardon';
    case TravelOrder = 'travel_order';
    case OvertimeRequest = 'overtime_request';
    case OfficialTime = 'official_time';

    public function label(): string
    {
        return match ($this) {
            self::LeaveApplication => 'Leave Application',
            self::Pardon => 'Pardon (Time Correction)',
            self::TravelOrder => 'Travel Order',
            self::OvertimeRequest => 'Overtime Request',
            self::OfficialTime => 'Official Time',
        };
    }
}
