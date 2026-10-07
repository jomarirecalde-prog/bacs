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
}
