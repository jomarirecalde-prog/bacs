<?php

namespace App\Enums;

enum TravelTransportation: string
{
    case Land = 'land';
    case Air = 'air';
    case Sea = 'sea';
    case CompanyVehicle = 'company_vehicle';
    case PublicTransportation = 'public_transportation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Land => 'Land',
            self::Air => 'Air',
            self::Sea => 'Sea',
            self::CompanyVehicle => 'Company Vehicle',
            self::PublicTransportation => 'Public Transportation',
            self::Other => 'Other',
        };
    }
}
