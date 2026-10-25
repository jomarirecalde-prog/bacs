<?php

namespace App\Enums;

enum ManualSalaryComputationStatus: string
{
    case Draft = 'draft';
    case ForReview = 'for_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ForReview => 'For Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Finalized => 'Finalized',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Finalized, self::Cancelled], true);
    }

    /** @return list<self> */
    public static function payableBlocking(): array
    {
        return [self::ForReview, self::Approved, self::Finalized];
    }
}
