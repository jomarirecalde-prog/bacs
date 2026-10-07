<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DesignationSeeder extends Seeder
{
    /** @var list<string> */
    private array $defaults = [
        'CEO / President',
        'Project Manager',
        'Operation Manager',
        'Admin Manager',
        'Finance Manager',
        'Technical Head',
        'Project Survey Head',
        'Project Technical Supervisor',
        'Junior Office Engineer',
        'Technical Staff',
        'Draftsman',
        'Laboratory Operator',
        'Procurement Officer',
        'Admin Assistant',
        'EHS Head',
        'Company Driver',
        'GSS',
        'IT',
        'Accounting Clerk',
        'Bookkeeper',
        'Field Engineer',
        'Field Staff',
        'Project Team Leader',
        'Assistant Field Engineer',
    ];

    public function run(): void
    {
        foreach ($this->defaults as $name) {
            $code = Str::upper(Str::slug($name, '_'));
            if ($code === '') {
                continue;
            }

            Designation::query()->firstOrCreate(
                ['designation_name' => $name],
                [
                    'designation_code' => Str::limit($code, 64, ''),
                    'is_active' => true,
                    'default_working_hours_per_day' => 8,
                ]
            );
        }
    }
}
