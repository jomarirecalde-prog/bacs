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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EmployeeSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'password';

    public const EXPECTED_COUNT = 46;

    public const EXPECTED_BY_DEPARTMENT = [
        'BOARD OF DIRECTORS AND CORPORATE OFFICERS' => 5,
        'PROJECT MANAGEMENT' => 9,
        'SALES & MARKETING' => 2,
        'ADMIN' => 9,
        'FINANCE' => 4,
        'OPERATION' => 17,
    ];

    public function run(): void
    {
        $schedule = WorkSchedule::defaultSchedule();
        $departments = Department::query()->pluck('id', 'name');
        $credentials = [];
        $officialNumbers = [];

        foreach ($this->roster() as $person) {
            $departmentName = $person['department'];
            $departmentId = $departments[$departmentName] ?? null;

            if (! $departmentId) {
                throw new \RuntimeException("Department [{$departmentName}] was not found. Run DepartmentSeeder first.");
            }

            $employeeNumber = $this->employeeNumber($person['id']);
            $officialNumbers[] = $employeeNumber;
            $parsed = $this->parseName($person['name']);
            $email = 'bacs.2026.'.sprintf('%04d', $person['id']).'@bacs.test';
            $role = $person['role'] ?? UserRole::Employee;

            $employee = Employee::query()->where('employee_number', $employeeNumber)->first();
            $user = $employee?->user ?? User::query()->where('username', $employeeNumber)->first();

            $userValues = [
                'username' => $employeeNumber,
                'name' => $person['name'],
                'email' => $email,
                'role' => $role,
                'status' => AccountStatus::Active,
                'password' => self::DEFAULT_PASSWORD,
                'must_change_password' => false,
            ];

            if ($user) {
                $user->fill($userValues);
                $user->save();
            } else {
                $user = User::query()->create($userValues);
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
                    'contact_number' => $employee?->contact_number,
                    'department_id' => $departmentId,
                    'position' => $person['position'],
                    'employment_status' => $employee?->employment_status ?? EmploymentStatus::Regular,
                    'date_hired' => $employee?->date_hired?->toDateString() ?? '2026-01-01',
                    'work_schedule_id' => $employee?->work_schedule_id ?? $schedule?->id,
                ]
            );

            $credentials[] = [
                $employeeNumber,
                $person['name'],
                $departmentName,
                $person['position'],
                $email,
                self::DEFAULT_PASSWORD,
            ];
        }

        $this->retireStaleEmployees($officialNumbers);
        $this->assertRosterIntegrity();
        $this->writeCredentials($credentials);

        $count = Employee::query()->where('employee_number', 'like', 'BACS-2026-%')->active()->count();
        $this->command?->info("Employee master data ready: {$count} employees (password: ".self::DEFAULT_PASSWORD.').');
    }

    /**
     * Official roster from EMP. ID Number.pdf.
     *
     * @return list<array{id: int, department: string, name: string, position: string, role?: UserRole}>
     */
    public function roster(): array
    {
        $board = 'BOARD OF DIRECTORS AND CORPORATE OFFICERS';
        $project = 'PROJECT MANAGEMENT';
        $sales = 'SALES & MARKETING';
        $admin = 'ADMIN';
        $finance = 'FINANCE';
        $operation = 'OPERATION';
        $supervisor = UserRole::Supervisor;

        return [
            ['id' => 1, 'department' => $board, 'name' => 'Bacosa, Cesario Jr. A.', 'position' => 'CEO / President', 'role' => $supervisor],
            ['id' => 2, 'department' => $board, 'name' => 'Bacosa, Katherine J.', 'position' => 'Chief Finance Officer', 'role' => $supervisor],
            ['id' => 3, 'department' => $board, 'name' => 'Germina, Mark Jayson H.', 'position' => 'Chief Technical and Operations Officer', 'role' => $supervisor],
            ['id' => 4, 'department' => $board, 'name' => 'Beltran, Lyn M.', 'position' => 'Chief Administrative Officer', 'role' => $supervisor],
            ['id' => 48, 'department' => $board, 'name' => 'Grageda, Riza E.', 'position' => 'Chief Business and Development Officer and HR Officer', 'role' => $supervisor],
            ['id' => 5, 'department' => $admin, 'name' => 'Gaid, Dorabel Y.', 'position' => 'Admin Assistant'],
            ['id' => 6, 'department' => $operation, 'name' => 'Lagrosa, Noibo A.', 'position' => 'Field Staff'],
            ['id' => 7, 'department' => $finance, 'name' => 'Acompañado, Nancy G.', 'position' => 'Accounts Receivable Officer'],
            ['id' => 8, 'department' => $finance, 'name' => 'Consuelo, Regine C.', 'position' => 'Finance Assistant'],
            ['id' => 9, 'department' => $operation, 'name' => 'Abis, Oscar D.', 'position' => 'Field Staff'],
            ['id' => 10, 'department' => $project, 'name' => 'Malazarte, Jan Kayle Mari', 'position' => 'Technical Staff'],
            ['id' => 11, 'department' => $admin, 'name' => 'Guerzo, Jovelyn H.', 'position' => 'EHS Head'],
            ['id' => 12, 'department' => $admin, 'name' => 'Macatangay, Michael G.', 'position' => 'Company Driver'],
            ['id' => 13, 'department' => $finance, 'name' => 'Dobleros, Reden D.', 'position' => 'Accounting Staff'],
            ['id' => 14, 'department' => $operation, 'name' => 'Cayapas, Reymond I.', 'position' => 'Project Team Leader'],
            ['id' => 15, 'department' => $admin, 'name' => 'Alejandrino, Rey Mark A.', 'position' => 'GSS'],
            ['id' => 16, 'department' => $operation, 'name' => 'Morados, Moses O.', 'position' => 'Field Staff'],
            ['id' => 17, 'department' => $project, 'name' => 'Paredes, Matthew John Clifford D.', 'position' => 'Draftsman / Technical Staff'],
            ['id' => 19, 'department' => $operation, 'name' => 'Aldamar, Jorge Jr. A.', 'position' => 'Project Team Leader'],
            ['id' => 20, 'department' => $operation, 'name' => 'Macatangay, Norman G.', 'position' => 'Field Staff'],
            ['id' => 21, 'department' => $project, 'name' => 'Fernandez, Gerald S.', 'position' => 'Junior Office Engineer'],
            ['id' => 23, 'department' => $project, 'name' => 'Basa, Cris Janrey V.', 'position' => 'Laboratory Operator'],
            ['id' => 25, 'department' => $project, 'name' => 'Patricio, Mechelle E.', 'position' => 'Project Technical Supervisor'],
            ['id' => 27, 'department' => $admin, 'name' => 'De La Cruz, Kenneth S.', 'position' => 'Repair and Maintenance'],
            ['id' => 28, 'department' => $operation, 'name' => 'Elevera, Dondon A.', 'position' => 'Field Staff'],
            ['id' => 29, 'department' => $operation, 'name' => 'Becaro, Ronald D.', 'position' => 'Project Team Leader'],
            ['id' => 30, 'department' => $finance, 'name' => 'Herrera, Realyn S.', 'position' => 'Bookkeeper'],
            ['id' => 31, 'department' => $operation, 'name' => 'Mitchell, Rey John G.', 'position' => 'Project Team Leader'],
            ['id' => 32, 'department' => $admin, 'name' => 'Abrea, Allan James A.', 'position' => 'Staff'],
            ['id' => 33, 'department' => $operation, 'name' => 'Lorenzo, Jay Ar Rhyan A.', 'position' => 'Project Team Leader'],
            ['id' => 34, 'department' => $operation, 'name' => 'Leal, Emmanuel Robert M.', 'position' => 'Field Staff'],
            ['id' => 35, 'department' => $operation, 'name' => 'Gallardo, Gerald L.', 'position' => 'Field Staff'],
            ['id' => 36, 'department' => $sales, 'name' => 'Baroro, Jennyvell E.', 'position' => 'Junior Sales'],
            ['id' => 37, 'department' => $project, 'name' => 'Bungay, Janelle L.', 'position' => 'Technical Staff'],
            ['id' => 38, 'department' => $operation, 'name' => 'Cayupan, Edward M.', 'position' => 'Field Staff'],
            ['id' => 39, 'department' => $operation, 'name' => 'Cabasal, Mark John L.', 'position' => 'Field Staff'],
            ['id' => 42, 'department' => $sales, 'name' => 'Ruiz, Pierceval B.', 'position' => 'Sales and Marketing'],
            ['id' => 43, 'department' => $admin, 'name' => 'Pascual, Lydio Jr. G.', 'position' => 'Company Driver'],
            ['id' => 44, 'department' => $project, 'name' => 'Adriano, Willy Joseph F.', 'position' => 'Technical Staff'],
            ['id' => 45, 'department' => $admin, 'name' => 'Balmonte, Ricky B.', 'position' => 'Company Driver'],
            ['id' => 46, 'department' => $admin, 'name' => 'Capis, Melqui T.', 'position' => 'Company Driver'],
            ['id' => 47, 'department' => $operation, 'name' => 'Panes, Charwyn A.', 'position' => 'Field Staff'],
            ['id' => 49, 'department' => $project, 'name' => 'Paalan, Engelbert', 'position' => 'Laboratory Technician'],
            ['id' => 50, 'department' => $operation, 'name' => 'Balbin, Sajied', 'position' => 'Field Staff'],
            ['id' => 51, 'department' => $operation, 'name' => 'Elias, Ashari', 'position' => 'Field Staff'],
            ['id' => 52, 'department' => $project, 'name' => 'Edon, Cody Mae', 'position' => 'Asst. Field Engineer'],
        ];
    }

    public function employeeNumber(int $id): string
    {
        return sprintf('BACS-2026-%04d', $id);
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

    /**
     * @param  list<string>  $officialNumbers
     */
    private function retireStaleEmployees(array $officialNumbers): void
    {
        Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->whereNotIn('employee_number', $officialNumbers)
            ->with('user')
            ->get()
            ->each(function (Employee $employee) {
                $employee->user?->update(['status' => AccountStatus::Inactive]);
            });
    }

    private function assertRosterIntegrity(): void
    {
        $count = Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->active()
            ->count();

        if ($count !== self::EXPECTED_COUNT) {
            throw new \RuntimeException('Expected '.self::EXPECTED_COUNT." active BACS employees, found {$count}.");
        }

        foreach (self::EXPECTED_BY_DEPARTMENT as $department => $expected) {
            $actual = Employee::query()
                ->where('employee_number', 'like', 'BACS-2026-%')
                ->active()
                ->whereHas('department', fn ($q) => $q->where('name', $department))
                ->count();

            if ($actual !== $expected) {
                throw new \RuntimeException("Expected {$expected} employees in {$department}, found {$actual}.");
            }
        }

        $unlinked = Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->active()
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereDoesntHave('user');
            })
            ->count();

        if ($unlinked > 0) {
            throw new \RuntimeException("Found {$unlinked} BACS employees missing a department or user account.");
        }

        $uniqueNumbers = Employee::query()
            ->where('employee_number', 'like', 'BACS-2026-%')
            ->active()
            ->pluck('employee_number')
            ->unique()
            ->count();

        if ($uniqueNumbers !== self::EXPECTED_COUNT) {
            throw new \RuntimeException('BACS employee numbers are not unique.');
        }

        $badPasswords = User::query()
            ->whereHas('employee', fn ($q) => $q->where('employee_number', 'like', 'BACS-2026-%')->active())
            ->get()
            ->reject(fn (User $user) => Hash::check(self::DEFAULT_PASSWORD, $user->password))
            ->count();

        if ($badPasswords > 0) {
            throw new \RuntimeException("Found {$badPasswords} employee accounts without the default password.");
        }
    }

    private function writeCredentials(array $rows): void
    {
        $lines = [
            'BACS CONSTRUCTION AND DEVELOPMENT CORPORATION',
            'Employee login credentials from EMP. ID Number.pdf',
            'Username is the employee number. Default password for all accounts: '.self::DEFAULT_PASSWORD,
            '',
            'Employee Number,Name,Department,Position,Email,Password',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row));
        }

        Storage::disk('local')->put('seed-credentials.csv', implode(PHP_EOL, $lines).PHP_EOL);
        $this->command?->info('Credentials written to storage/app/private/seed-credentials.csv');
    }
}
