<?php

namespace App\Enums;

enum StationActivityResult: string
{
    case Success = 'success';
    case Failure = 'failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Failure => 'Failure',
        };
    }
}
