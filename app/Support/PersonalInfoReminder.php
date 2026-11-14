<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\CarbonInterface;

final class PersonalInfoReminder
{
    /** @var list<array{key: string, label: string}> */
    public const FIELDS = [
        ['key' => 'contact_number', 'label' => 'contact number'],
        ['key' => 'address', 'label' => 'address'],
        ['key' => 'birth_date', 'label' => 'birth date'],
    ];

    /**
     * @return list<string>
     */
    public static function missingFieldLabels(?Employee $employee): array
    {
        if (! $employee) {
            return [];
        }

        $labels = [];

        foreach (self::FIELDS as $field) {
            if (self::isEmpty($employee->{$field['key']} ?? null)) {
                $labels[] = $field['label'];
            }
        }

        return $labels;
    }

    public static function shouldRemind(?Employee $employee): bool
    {
        return self::missingFieldLabels($employee) !== [];
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($value instanceof CarbonInterface) {
            return false;
        }

        return trim((string) $value) === '';
    }
}