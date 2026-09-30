<?php

namespace App\Enums;

enum TravelOrderStatus: string
{
    case Draft = 'draft';
    case PendingSupervisor = 'pending_supervisor';
    case PendingDepartmentHead = 'pending_department_head';
    case PendingAdministrativeHead = 'pending_administrative_head';
    case PendingCeoFinalApproval = 'pending_ceo_final_approval';
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Denied = 'denied';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Pending Endorsement',
            self::PendingDepartmentHead => 'Pending Department Head',
            self::PendingAdministrativeHead => 'Pending Administrative Head',
            self::PendingCeoFinalApproval => 'Pending Final Approval',
            self::PartiallyApproved => 'Partially Endorsed',
            self::Approved => 'Approved',
            self::Denied => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'brand',
            self::Denied, self::Cancelled => 'critical',
            self::PartiallyApproved => 'gold',
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
            self::PendingDepartmentHead,
            self::PendingAdministrativeHead,
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
            self::PendingSupervisor => LeaveApprovalStage::ImmediateSupervisor,
            self::PendingDepartmentHead => LeaveApprovalStage::DepartmentHead,
            self::PendingAdministrativeHead => LeaveApprovalStage::AdministrativeHead,
            self::PendingCeoFinalApproval => LeaveApprovalStage::CeoFinalApproval,
            self::PartiallyApproved => LeaveApprovalStage::ImmediateSupervisor,
            default => null,
        };
    }
}
