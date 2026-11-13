<?php

namespace App\Enums;

enum ApprovalTransactionType: string
{
    case LeaveApplication = 'leave_application';
    case Pardon = 'pardon';
    case TravelOrder = 'travel_order';
    case OvertimeRequest = 'overtime_request';
    case OfficialTime = 'official_time';
    case OvertimeAttachmentReport = 'overtime_attachment_report';
    case OfficialBusiness = 'official_business';

    /** @return list<self> Transaction types that share one workflow in Settings (excludes pardon). */
    public static function unifiedTypes(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type) => $type !== self::Pardon && $type !== self::OfficialBusiness,
        ));
    }

    /** Official Business uses the Travel Order approval configuration (endorsers, modes, rules). */
    public function centralWorkflowType(): self
    {
        return match ($this) {
            self::OfficialBusiness => self::TravelOrder,
            default => $this,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LeaveApplication => 'Leave Application',
            self::Pardon => 'Pardon (Time Correction)',
            self::TravelOrder => 'Travel Order',
            self::OvertimeRequest => 'Overtime Request',
            self::OfficialTime => 'Official Time',
            self::OvertimeAttachmentReport => 'OT Attachment Report',
            self::OfficialBusiness => 'Official Business',
        };
    }
}
