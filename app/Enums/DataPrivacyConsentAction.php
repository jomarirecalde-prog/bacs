<?php

namespace App\Enums;

enum DataPrivacyConsentAction: string
{
    case Granted = 'granted';
    case Declined = 'declined';
}
