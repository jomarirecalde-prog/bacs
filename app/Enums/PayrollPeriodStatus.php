<?php

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Computed = 'computed';
    case ForReview = 'for_review';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Computed => 'Computed',
            self::ForReview => 'For Review',
            self::Approved => 'Approved',
            self::Finalized => 'Finalized',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Finalized, self::Paid], true);
    }

    public function allowsRecomputation(): bool
    {
        return ! $this->isLocked() && $this !== self::Cancelled;
    }

    /** Employee-facing payroll pipeline label (My Salary). */
    public function employeeSalaryLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Computed, self::ForReview => 'Processing',
            self::Approved => 'Approved',
            self::Finalized, self::Paid => 'Released',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<self> */
    public static function forEmployeeSalaryFilter(string $filter): array
    {
        return match ($filter) {
            'draft' => [self::Draft],
            'processing' => [self::Computed, self::ForReview],
            'approved' => [self::Approved],
            'released' => [self::Finalized, self::Paid],
            default => [],
        };
    }

    public function employeeAmountKind(): string
    {
        if ($this->isLocked()) {
            return 'released';
        }

        if ($this === self::Approved) {
            return 'approved';
        }

        if ($this === self::Cancelled) {
            return 'none';
        }

        return 'estimate';
    }
}
