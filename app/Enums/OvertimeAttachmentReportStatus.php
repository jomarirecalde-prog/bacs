<?php

namespace App\Enums;

enum OvertimeAttachmentReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted, self::UnderReview, self::Verified => 'Pending payroll review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'brand',
            self::Rejected, self::Cancelled => 'critical',
            self::Verified => 'gold',
            self::Returned => 'warn',
            self::Draft => 'neutral',
            default => 'warn',
        };
    }

    public function badgeClass(): string
    {
        return match ($this->tone()) {
            'brand' => 'badge-brand',
            'critical' => 'badge-critical',
            'gold' => 'badge-gold',
            'neutral' => 'badge-neutral',
            default => 'badge-warn',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::UnderReview,
            self::Verified,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }

    public function currentStage(): ?LeaveApprovalStage
    {
        return match ($this) {
            self::UnderReview, self::Verified => LeaveApprovalStage::ImmediateSupervisor,
            default => null,
        };
    }
}
