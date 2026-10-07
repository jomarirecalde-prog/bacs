<?php

use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        $this->backfillFromPositionText();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
        });
    }

    private function backfillFromPositionText(): void
    {
        $positions = Employee::query()
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->distinct()
            ->pluck('position');

        foreach ($positions as $position) {
            $normalized = trim((string) $position);
            if ($normalized === '') {
                continue;
            }

            Designation::query()->firstOrCreate(
                ['designation_name' => $normalized],
                [
                    'designation_code' => $this->uniqueCode($normalized),
                    'is_active' => true,
                ]
            );
        }

        Employee::query()
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->chunkById(100, function ($employees) {
                foreach ($employees as $employee) {
                    $designation = Designation::query()
                        ->where('designation_name', trim((string) $employee->position))
                        ->first();

                    if ($designation) {
                        $employee->update(['designation_id' => $designation->id]);
                    }
                }
            });
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'DESIGNATION';
        }
        $base = Str::limit($base, 48, '');

        $code = $base;
        $suffix = 1;
        while (Designation::query()->where('designation_code', $code)->exists()) {
            $code = Str::limit($base, 44, '').'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
};
