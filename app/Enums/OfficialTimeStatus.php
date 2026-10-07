<?php

namespace App\Enums;

enum OfficialTimeStatus: string
{
    case Draft = 'draft';
    case PendingSupervisor = 'pending_supervisor';
    case PendingCeoFinalApproval = 'pending_ceo_final_approval';
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Denied = 'denied';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'For Endorsement',
            self::PendingCeoFinalApproval => 'For Approval',
            self::PartiallyApproved => 'Partially Endorsed',
            self::Approved => 'Approved',
            self::Denied => 'Rejected',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'brand',
            self::Denied, self::Cancelled => 'critical',
            self::PartiallyApproved => 'gold',
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
            self::PendingSupervisor,
            self::PendingCeoFinalApproval,
            self::PartiallyApproved,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Denied, self::Cancelled], true);
    }

    public function currentStage(): ?LeaveApprovalStage
    {
        return match ($this) {
            self::PendingSupervisor, self::PartiallyApproved => LeaveApprovalStage::ImmediateSupervisor,
            self::PendingCeoFinalApproval => LeaveApprovalStage::CeoFinalApproval,
            default => null,
        };
    }
}
