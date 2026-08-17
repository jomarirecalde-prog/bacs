<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public const DEPARTMENTS = [
        ['name' => 'BOARD OF DIRECTORS AND CORPORATE OFFICERS', 'description' => 'Board of Directors and Corporate Officers', 'sort_order' => 1],
        ['name' => 'PROJECT MANAGEMENT', 'description' => 'Project Management', 'sort_order' => 2],
        ['name' => 'SALES & MARKETING', 'description' => 'Sales and Marketing', 'sort_order' => 3],
        ['name' => 'ADMIN', 'description' => 'Administration', 'sort_order' => 4],
        ['name' => 'FINANCE', 'description' => 'Finance', 'sort_order' => 5],
        ['name' => 'OPERATION', 'description' => 'Operation', 'sort_order' => 6],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $department) {
            Department::query()->updateOrCreate(
                ['name' => $department['name']],
                [
                    'description' => $department['description'],
                    'sort_order' => $department['sort_order'],
                    'status' => AccountStatus::Active,
                ]
            );
        }
    }
}
