<?php

namespace App\Enums;

enum LeaveApprovalStage: string
{
    case ImmediateSupervisor = 'immediate_supervisor';
    case DepartmentHead = 'department_head';
    case AdministrativeHead = 'administrative_head';
    case CeoFinalApproval = 'ceo_final_approval';
    case HrOfficer = 'hr_officer';

    public function label(): string
    {
        return match ($this) {
            self::ImmediateSupervisor => 'Immediate Supervisor/Superior Approval',
            self::DepartmentHead => 'Department Head',
            self::AdministrativeHead => 'Administrative Head',
            self::CeoFinalApproval => 'CEO — Final Approval',
            self::HrOfficer => 'HR Processing',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::ImmediateSupervisor => 'Immediate Supervisor/Superior',
            self::DepartmentHead => 'Department Head',
            self::AdministrativeHead => 'Administrative Head',
            self::CeoFinalApproval => 'CEO',
            self::HrOfficer => 'HR Processing',
        };
    }

    public function isParallel(): bool
    {
        return $this === self::ImmediateSupervisor;
    }

    public function isFinalApproval(): bool
    {
        return $this === self::CeoFinalApproval;
    }

    public function pendingStatus(): LeaveStatus
    {
        return match ($this) {
            self::ImmediateSupervisor => LeaveStatus::PendingSupervisor,
            self::DepartmentHead => LeaveStatus::PendingDepartmentHead,
            self::AdministrativeHead => LeaveStatus::PendingAdministrativeHead,
            self::CeoFinalApproval => LeaveStatus::PendingCeoFinalApproval,
            self::HrOfficer => LeaveStatus::PendingHr,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::ImmediateSupervisor => self::DepartmentHead,
            self::DepartmentHead => self::AdministrativeHead,
            self::AdministrativeHead => self::CeoFinalApproval,
            self::CeoFinalApproval, self::HrOfficer => null,
        };
    }

    /** @return list<self> */
    public static function sequence(): array
    {
        return [
            self::ImmediateSupervisor,
            self::DepartmentHead,
            self::AdministrativeHead,
            self::CeoFinalApproval,
        ];
    }

    /** @return list<self> Stages configurable per department by Superadmin. */
    public static function configurable(): array
    {
        return [
            self::ImmediateSupervisor,
            self::DepartmentHead,
            self::AdministrativeHead,
        ];
    }
}
