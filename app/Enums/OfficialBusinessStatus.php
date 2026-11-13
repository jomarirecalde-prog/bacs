<?php

namespace App\Enums;

enum OfficialBusinessStatus: string
{
    case Draft = 'draft';
    case PendingSupervisor = 'pending_supervisor';
    case PendingDepartmentHead = 'pending_department_head';
    case PendingAdministrativeHead = 'pending_administrative_head';
    case PendingCeoFinalApproval = 'pending_ceo_final_approval';
    case PendingApproverAssignment = 'pending_approver_assignment';
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Denied = 'denied';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Submitted',
            self::PendingDepartmentHead, self::PartiallyApproved => 'Under Review',
            self::PendingAdministrativeHead, self::PendingCeoFinalApproval, self::PendingApproverAssignment => 'Verified',
            self::Approved => 'Approved',
            self::Denied => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved, self::Completed => 'brand',
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
            self::PendingApproverAssignment,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Denied, self::Cancelled, self::Completed], true);
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
