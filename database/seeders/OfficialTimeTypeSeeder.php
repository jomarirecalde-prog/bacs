<?php

namespace Database\Seeders;

use App\Models\OfficialTimeType;
use Illuminate\Database\Seeder;

class OfficialTimeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'official_meeting', 'name' => 'Official Meeting'],
            ['code' => 'training', 'name' => 'Training'],
            ['code' => 'seminar', 'name' => 'Seminar'],
            ['code' => 'conference', 'name' => 'Conference'],
            ['code' => 'field_assignment', 'name' => 'Field Assignment', 'requires_location' => true],
            ['code' => 'official_business', 'name' => 'Official Business'],
            ['code' => 'government_transaction', 'name' => 'Government Transaction', 'requires_attachment' => true],
            ['code' => 'company_activity', 'name' => 'Company Activity'],
            ['code' => 'representation', 'name' => 'Representation'],
            ['code' => 'other', 'name' => 'Other'],
        ];

        foreach ($types as $row) {
            OfficialTimeType::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'requires_attachment' => (bool) ($row['requires_attachment'] ?? false),
                    'requires_location' => (bool) ($row['requires_location'] ?? false),
                    'is_active' => true,
                ]
            );
        }
    }
}
