<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\PersonalInfoReminder;
use Tests\TestCase;

class PersonalInfoReminderTest extends TestCase
{
    public function test_lists_missing_optional_personal_fields(): void
    {
        $employee = new Employee([
            'contact_number' => '',
            'address' => null,
            'birth_date' => null,
        ]);

        $this->assertSame(
            ['contact number', 'address', 'birth date'],
            PersonalInfoReminder::missingFieldLabels($employee),
        );
    }

    public function test_returns_empty_when_all_reminder_fields_present(): void
    {
        $employee = new Employee([
            'contact_number' => '09171234567',
            'address' => '123 Main St',
            'birth_date' => '1990-01-15',
        ]);

        $this->assertSame([], PersonalInfoReminder::missingFieldLabels($employee));
        $this->assertFalse(PersonalInfoReminder::shouldRemind($employee));
    }
}