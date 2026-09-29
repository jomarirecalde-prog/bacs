<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class EmailBranding
{
    public static function logoUrl(): string
    {
        $configured = config('bacs.mail_logo_url');
        if (filled($configured)) {
            return $configured;
        }

        return URL::to('/images/bacs_logo_no_bg.png');
    }

    /** @return array<string, mixed> */
    public static function layoutContext(): array
    {
        return [
            'companyName' => config('bacs.company_name'),
            'systemName' => config('bacs.system_name'),
            'logoUrl' => self::logoUrl(),
            'loginUrl' => route('login'),
            'year' => now()->format('Y'),
        ];
    }

    public static function badgeStyle(string $badge): string
    {
        return match ($badge) {
            'approved' => 'background-color:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;',
            'rejected' => 'background-color:#fef2f2;color:#991b1b;border:1px solid #fecaca;',
            'account_created' => 'background-color:#eff6ff;color:#1e40af;border:1px solid #93c5fd;',
            default => 'background-color:#fffbeb;color:#92400e;border:1px solid #fcd34d;',
        };
    }
}
