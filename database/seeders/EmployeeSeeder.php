<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public const EXPECTED_COUNT = 44;

    public const EXPECTED_BY_DEPARTMENT = [
        'BOARD OF DIRECTORS AND CORPORATE OFFICERS' => 5,
        'PROJECT MANAGEMENT' => 8,
        'SALES & MARKETING' => 2,
        'ADMIN' => 9,
        'FINANCE' => 4,
        'OPERATION' => 16,
    ];

    public function run(): void
    {
        $schedule = WorkSchedule::defaultSchedule();
        $departments = Department::query()->pluck('id', 'name');
        $credentials = [];

        foreach ($this->roster() as $departmentName => $people) {
            $departmentId = $departments[$departmentName] ?? null;

            if (! $departmentId) {
                throw new \RuntimeException("Department [{$departmentName}] was not found. Run DepartmentSeeder first.");
            }

            foreach ($people as $person) {
                $existing = Employee::query()
                    ->where('full_name', $person['name'])
                    ->where('department_id', $departmentId)
                    ->first();

                $employeeNumber = $existing?->employee_number ?? $this->nextEmployeeNumber();
                $parsed = $this->parseName($person['name']);
                $sequence = (int) substr($employeeNumber, -4);
                $email = $existing?->email ?: 'bacs.2026.'.sprintf('%04d', $sequence).'@bacs.test';
                $role = $person['role'] ?? UserRole::Employee;

                $user = $existing?->user ?? User::query()->where('username', $employeeNumber)->first();
                $isNewUser = $user === null;
                $temporaryPassword = $isNewUser ? Str::password(12, symbols: false) : null;

                $userValues = [
                    'username' => $employeeNumber,
                    'name' => $person['name'],
                    'email' => $email,
                    'role' => $role,
                    'status' => AccountStatus::Active,
                ];

                if ($isNewUser) {
                    $userValues['password'] = $temporaryPassword;
                    $userValues['must_change_password'] = true;
                    $user = User::query()->create($userValues);
                } else {
                    $user->fill($userValues);
                    $user->save();
                }

                Employee::query()->updateOrCreate(
                    ['employee_number' => $employeeNumber],
                    [
                        'user_id' => $user->id,
                        'first_name' => $parsed['first_name'],
                        'middle_name' => $parsed['middle_name'],
                        'last_name' => $parsed['last_name'],
                        'full_name' => $person['name'],
                        'email' => $email,
                        'contact_number' => $existing?->contact_number,
                        'department_id' => $departmentId,
                        'position' => $person['position'],
                        'employment_status' => $existing?->employment_status ?? EmploymentStatus::Regular,
                        'date_hired' => $existing?->date_hired?->toDateString() ?? '2026-01-01',
                        'work_schedule_id' => $existing?->work_schedule_id ?? $schedule?->id,
                    ]
                );

                if ($isNewUser && $temporaryPassword) {
                    $credentials[] = [
                        $employeeNumber,
                        $person['name'],
                        $departmentName,
                        $person['position'],
                        $email,
                        $temporaryPassword,
                    ];
                }
            }
        }

        $this->assertRosterIntegrity();
        $this->writeCredentials($credentials);

        $count = Employee::query()->where('employee_number', 'like', 'BACS-2026-%')->count();
        $this->command?->info("Employee master data ready: {$count} employees.");
    }

    /**
     * @return array<string, list<array{name: string, position: string, role?: UserRole}>>
     */
    public function roster(): array
    {
        return [
            'BOARD OF DIRECTORS AND CORPORATE OFFICERS' => [
                ['name' => 'Bacosa, Cesario Jr', 'position' => 'CEO / Chairman of the Board', 'role' => UserRole::Supervisor],
                ['name' => 'Germina, Mark Jayson H', 'position' => 'Chief Technical & Operation Officer / Operation & Project Manager', 'role' => UserRole::Supervisor],
                ['name' => 'Beltran, Lyn M.', 'position' => 'Chief Administrative Officer / Admin Manager', 'role' => UserRole::Supervisor],
                ['name' => 'Bacosa, Katherine J.', 'position' => 'Chief Finance Officer / Finance Manager', 'role' => UserRole::Supervisor],
                ['name' => 'Grageda, Riza', 'position' => 'Chief Business Development Officer / HR Officer', 'role' => UserRole::Supervisor],
            ],
            'PROJECT MANAGEMENT' => [
                ['name' => 'Paalan, Engelbert', 'position' => 'Laboratory Operator'],
                ['name' => 'Patricio, Mechelle', 'position' => 'Project Technical Supervisor'],
                ['name' => 'Fernandez, Gerald', 'position' => 'Junior Office Engineer'],
                ['name' => 'Malazarte, Jan Kayle Mari', 'position' => 'Technical Staff'],
                ['name' => 'Paredes, Matthew', 'position' => 'Draftsman'],
                ['name' => 'Bungay, Janelle', 'position' => 'Technical Staff'],
                ['name' => 'Basa, Cris Janrey', 'position' => 'Laboratory Operator'],
                ['name' => 'Adriano, Willy Joseph', 'position' => 'Technical Staff'],
            ],
            'SALES & MARKETING' => [
                ['name' => 'Baroro, Jennyvell Jr.', 'position' => 'Sales Representative'],
                ['name' => 'Ruiz, Pierceval', 'position' => 'Sales & Marketing Staff'],
            ],
            'ADMIN' => [
                ['name' => 'Gaid, Dorabel Y.', 'position' => 'Admin Assistant'],
                ['name' => 'Guerzo, Jovelyn H.', 'position' => 'Environment, Health & Safety Head'],
                ['name' => 'Macatangay, Michael', 'position' => 'Company Driver'],
                ['name' => 'Alejandrino, Rey Mark', 'position' => 'General Support Services'],
                ['name' => 'Dela Cruz, Kenneth', 'position' => 'Repair & Maintenance'],
                ['name' => 'Abrea, Allan James', 'position' => 'Staff'],
                ['name' => 'Pascual, Lydio Jr.', 'position' => 'Company Driver'],
                ['name' => 'Balmonte, Ricky', 'position' => 'Company Driver'],
                ['name' => 'Capis, Melqui', 'position' => 'Company Driver'],
            ],
            'FINANCE' => [
                ['name' => 'Acompañado, Nancy', 'position' => 'Accounts Receivable Officer'],
                ['name' => 'Consuelo, Regine', 'position' => 'Finance Assistant'],
                ['name' => 'Dobleros, Reden', 'position' => 'Accounting Clerk'],
                ['name' => 'Herrera, Realyn', 'position' => 'Bookkeeper'],
            ],
            'OPERATION' => [
                ['name' => 'Cayapas, Reymond', 'position' => 'Field Engineer'],
                ['name' => 'Morados, Moses', 'position' => 'Field Staff'],
                ['name' => 'Aldamar, Jorge Jr.', 'position' => 'Project Team Leader'],
                ['name' => 'Mitchell, Rey John', 'position' => 'Project Team Leader'],
                ['name' => 'Becaro, Ronald', 'position' => 'Project Team Leader'],
                ['name' => 'Abis, Oscar', 'position' => 'Field Staff'],
                ['name' => 'Lagrosa, Noibo', 'position' => 'Field Staff'],
                ['name' => 'Macatangay, Norman', 'position' => 'Field Staff'],
                ['name' => 'Elevera, Dondon', 'position' => 'Field Staff'],
                ['name' => 'Cabasal, Mark John', 'position' => 'Field Staff'],
                ['name' => 'Capuyan, Edward', 'position' => 'Field Staff'],
                ['name' => 'Panes, Charwyn', 'position' => 'Field Staff'],
                ['name' => 'Leal, Emmanuel Robert', 'position' => 'Field Staff'],
                ['name' => 'Lorenzo, Jay Ar Rhyan', 'position' => 'Project Team Leader'],
                ['name' => 'Gallardo, Gerald', 'position' => 'Field Staff'],
                ['name' => 'Balbin, Sajied', 'position' => 'Field Staff'],
            ],
        ];
    }

    /**
     * @return array{first_name: string, middle_name: ?string, last_name: string}
     */
    public function parseName(string $displayName): array
    {
        [$lastName, $given] = array_map('trim', explode(',', $displayName, 2) + [1 => '']);
        $firstName = $given;
        $middleName = null;

        if (preg_match('/^(.*)\s+([A-Za-z]\.?)$/u', $given, $matches) === 1) {
            $firstName = trim($matches[1]);
            $middleName = $matches[2];
        }

        return [
            'first_name' => $firstName !== '' ? $firstName : $displayName,
            'middle_name' => $middleName,
            'last_name' => $lastName !== '' ? $lastName : $displayName,
        ];
    }

    private function nextEmployeeNumber(): string
    {
        $last = Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->pluck('employee_number')
            ->map(fn (string $number) => (int) substr($number, -4))
            ->max();

        return sprintf('BACS-2026-%04d', ((int) $last) + 1);
    }

    private function assertRosterIntegrity(): void
    {
        $count = Employee::query()->where('employee_number', 'like', 'BACS-2026-%')->count();

        if ($count !== self::EXPECTED_COUNT) {
            throw new \RuntimeException('Expected '.self::EXPECTED_COUNT." BACS employees, found {$count}.");
        }

        foreach (self::EXPECTED_BY_DEPARTMENT as $department => $expected) {
            $actual = Employee::query()
                ->where('employee_number', 'like', 'BACS-2026-%')
                ->whereHas('department', fn ($q) => $q->where('name', $department))
                ->count();

            if ($actual !== $expected) {
                throw new \RuntimeException("Expected {$expected} employees in {$department}, found {$actual}.");
            }
        }

        $unlinked = Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereDoesntHave('user');
            })
            ->count();

        if ($unlinked > 0) {
            throw new \RuntimeException("Found {$unlinked} BACS employees missing a department or user account.");
        }

        $uniqueNumbers = Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->pluck('employee_number')
            ->unique()
            ->count();

        if ($uniqueNumbers !== self::EXPECTED_COUNT) {
            throw new \RuntimeException('BACS employee numbers are not unique.');
        }
    }

    private function writeCredentials(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $lines = [
            'BACS CONSTRUCTION AND DEVELOPMENT CORPORATION',
            'Temporary employee login credentials — change on first login.',
            'Username is the employee number. Passwords are not stored in plain text in the database.',
            '',
            'Employee Number,Name,Department,Position,Email,Temporary Password',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row));
        }

        Storage::disk('local')->put('seed-credentials.csv', implode(PHP_EOL, $lines).PHP_EOL);
        $this->command?->warn('New temporary passwords written to storage/app/private/seed-credentials.csv');
    }
}
