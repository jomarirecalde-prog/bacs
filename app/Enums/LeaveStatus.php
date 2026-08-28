<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case PendingSupervisor = 'pending_supervisor';
    case PendingDepartmentHead = 'pending_department_head';
    case PendingAdministrativeHead = 'pending_administrative_head';
    case PendingCeoFinalApproval = 'pending_ceo_final_approval';
    case PendingHr = 'pending_hr';
    case Approved = 'approved';
    case Denied = 'denied';
    case PartiallyApproved = 'partially_approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingSupervisor => 'Pending Parallel Approval',
            self::PendingDepartmentHead => 'Pending Department Head',
            self::PendingAdministrativeHead => 'Pending Administrative Head',
            self::PendingCeoFinalApproval => 'Pending CEO Final Approval',
            self::PendingHr => 'Pending HR Processing',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::PartiallyApproved => 'Partially Approved',
            self::Cancelled => 'Cancelled by Employee',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'brand',
            self::Denied, self::Cancelled => 'critical',
            self::PartiallyApproved => 'gold',
            self::PendingSupervisor, self::PendingDepartmentHead, self::PendingAdministrativeHead, self::PendingCeoFinalApproval, self::PendingHr => 'warn',
        };
    }

    public function badgeClass(): string
    {
        return match ($this->tone()) {
            'brand' => 'badge-brand',
            'critical' => 'badge-critical',
            'gold' => 'badge-gold',
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
            self::PendingHr,
            self::PartiallyApproved,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Denied, self::Cancelled], true);
    }

    public function blocksOverlap(): bool
    {
        return $this->isOpen() || $this === self::Approved;
    }

    public function currentStage(): ?LeaveApprovalStage
    {
        return match ($this) {
            self::PendingSupervisor => LeaveApprovalStage::ImmediateSupervisor,
            self::PendingDepartmentHead => LeaveApprovalStage::DepartmentHead,
            self::PendingAdministrativeHead => LeaveApprovalStage::AdministrativeHead,
            self::PendingCeoFinalApproval => LeaveApprovalStage::CeoFinalApproval,
            self::PendingHr, self::PartiallyApproved => LeaveApprovalStage::HrOfficer,
            default => null,
        };
    }
}
