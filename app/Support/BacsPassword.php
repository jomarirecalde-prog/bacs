<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class BacsPassword
{
    public static function rule(): Password
    {
        return Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers();
    }
}
