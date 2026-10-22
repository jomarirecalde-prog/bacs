<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('approval_workflow_assignees', 'department_id')) {
            Schema::table('approval_workflow_assignees', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('workflow_configuration_id')->constrained()->cascadeOnDelete();
            });
        }

        $departmentIds = Department::query()->active()->pluck('id');
        if ($departmentIds->isEmpty()) {
            $departmentIds = Department::query()->pluck('id');
        }

        if ($departmentIds->isNotEmpty()) {
            $canonical = DB::table('approval_workflow_assignees')
                ->select([
                    'workflow_configuration_id',
                    'employee_id',
                    'approval_type',
                    DB::raw('MIN(sequence_order) as sequence_order'),
                    DB::raw('MAX(is_active) as is_active'),
                    DB::raw('MIN(created_at) as created_at'),
                    DB::raw('MAX(updated_at) as updated_at'),
                ])
                ->groupBy('workflow_configuration_id', 'employee_id', 'approval_type')
                ->get();

            DB::table('approval_workflow_assignees')->delete();
            $this->ensureWorkflowConfigurationIndex();
            $this->dropIndexIfExists('approval_wf_assignee_unique');

            $now = now();
            foreach ($canonical as $row) {
                foreach ($departmentIds as $departmentId) {
                    DB::table('approval_workflow_assignees')->insert([
                        'workflow_configuration_id' => $row->workflow_configuration_id,
                        'department_id' => $departmentId,
                        'employee_id' => $row->employee_id,
                        'approval_type' => $row->approval_type,
                        'sequence_order' => $row->sequence_order,
                        'is_active' => $row->is_active,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ]);
                }
            }
        } else {
            $this->ensureWorkflowConfigurationIndex();
            $this->dropIndexIfExists('approval_wf_assignee_unique');
        }

        if (! $this->indexExists('approval_wf_assignee_dept_unique')) {
            Schema::table('approval_workflow_assignees', function (Blueprint $table) {
                $table->unique(
                    ['workflow_configuration_id', 'department_id', 'employee_id', 'approval_type'],
                    'approval_wf_assignee_dept_unique'
                );
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('approval_wf_assignee_dept_unique');

        if (Schema::hasColumn('approval_workflow_assignees', 'department_id')) {
            Schema::table('approval_workflow_assignees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        if (! $this->indexExists('approval_wf_assignee_unique')) {
            Schema::table('approval_workflow_assignees', function (Blueprint $table) {
                $table->unique(
                    ['workflow_configuration_id', 'employee_id', 'approval_type'],
                    'approval_wf_assignee_unique'
                );
            });
        }
    }

    private function indexExists(string $name): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('approval_workflow_assignees')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $name) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select('SHOW INDEX FROM approval_workflow_assignees WHERE Key_name = ?', [$name]);

        return $rows !== [];
    }

    private function dropIndexIfExists(string $name): void
    {
        if (! $this->indexExists($name)) {
            return;
        }

        Schema::table('approval_workflow_assignees', function (Blueprint $table) use ($name) {
            $table->dropUnique($name);
        });
    }

    private function ensureWorkflowConfigurationIndex(): void
    {
        if ($this->indexExists('awf_workflow_configuration_id_index')) {
            return;
        }

        Schema::table('approval_workflow_assignees', function (Blueprint $table) {
            $table->index('workflow_configuration_id', 'awf_workflow_configuration_id_index');
        });
    }
};
