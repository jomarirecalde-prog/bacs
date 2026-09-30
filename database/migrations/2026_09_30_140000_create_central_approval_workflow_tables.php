<?php

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Enums\LeaveApprovalStage;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\LeaveApprovalWorkflow;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 32)->unique();
            $table->boolean('endorsement_enabled')->default(true);
            $table->boolean('final_approval_enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('approval_workflow_assignees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_configuration_id');
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('approval_type', 32);
            $table->unsignedSmallInteger('sequence_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['workflow_configuration_id', 'employee_id', 'approval_type'],
                'approval_wf_assignee_unique'
            );

            $table->foreign('workflow_configuration_id', 'awf_assign_config_fk')
                ->references('id')->on('approval_workflow_configurations')->cascadeOnDelete();
        });

        Schema::create('approval_workflow_configuration_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_configuration_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->text('summary');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->foreign('workflow_configuration_id', 'awf_hist_config_fk')
                ->references('id')->on('approval_workflow_configurations')->cascadeOnDelete();
            $table->foreign('updated_by', 'awf_hist_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('central_approval_config_id')->nullable()->after('workflow_version');
            $table->unsignedInteger('central_approval_config_version')->nullable()->after('central_approval_config_id');
            $table->foreign('central_approval_config_id', 'leave_apps_awf_config_fk')
                ->references('id')->on('approval_workflow_configurations')->nullOnDelete();
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('central_approval_config_id')->nullable()->after('workflow_version');
            $table->unsignedInteger('central_approval_config_version')->nullable()->after('central_approval_config_id');
            $table->foreign('central_approval_config_id', 'travel_orders_awf_config_fk')
                ->references('id')->on('approval_workflow_configurations')->nullOnDelete();
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('central_approval_config_id')->nullable()->after('status');
            $table->unsignedInteger('central_approval_config_version')->nullable()->after('central_approval_config_id');
            $table->string('current_approval_stage', 32)->nullable()->after('central_approval_config_version');
            $table->foreign('central_approval_config_id', 'pardon_req_awf_config_fk')
                ->references('id')->on('approval_workflow_configurations')->nullOnDelete();
        });

        Schema::create('attendance_correction_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_correction_request_id');
            $table->string('stage', 32);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('approver_name');
            $table->string('approver_position')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attendance_correction_request_id', 'stage', 'user_id'], 'pardon_assign_unique');
            $table->index(['user_id', 'status']);

            $table->foreign('attendance_correction_request_id', 'pardon_assign_req_fk')
                ->references('id')->on('attendance_correction_requests')->cascadeOnDelete();
            $table->foreign('user_id', 'pardon_assign_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('employee_id', 'pardon_assign_emp_fk')
                ->references('id')->on('employees')->nullOnDelete();
        });

        foreach (ApprovalTransactionType::cases() as $type) {
            DB::table('approval_workflow_configurations')->insert([
                'transaction_type' => $type->value,
                'endorsement_enabled' => true,
                'final_approval_enabled' => true,
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seedFromLegacyLeaveWorkflow();
    }

    private function seedFromLegacyLeaveWorkflow(): void
    {
        $workflow = LeaveApprovalWorkflow::query()->where('is_default', true)->with('approvers.user.employee')->first();
        if (! $workflow) {
            return;
        }

        $stages = [
            LeaveApprovalStage::ImmediateSupervisor,
            LeaveApprovalStage::DepartmentHead,
            LeaveApprovalStage::AdministrativeHead,
        ];

        $endorserIds = $workflow->approvers
            ->whereIn('stage', $stages)
            ->map(fn ($row) => $row->user?->employee?->id)
            ->filter()
            ->unique()
            ->values();

        $finalEmployeeId = null;
        $ceoUserId = Setting::get('ceo_user_id');
        if ($ceoUserId) {
            $finalEmployeeId = User::query()->find($ceoUserId)?->employee?->id;
        }

        foreach (ApprovalTransactionType::cases() as $type) {
            $config = ApprovalWorkflowConfiguration::query()->where('transaction_type', $type->value)->first();
            if (! $config) {
                continue;
            }

            foreach ($endorserIds as $index => $employeeId) {
                ApprovalWorkflowAssignee::query()->create([
                    'workflow_configuration_id' => $config->id,
                    'employee_id' => $employeeId,
                    'approval_type' => ApprovalAssigneeType::Endorser,
                    'sequence_order' => $index,
                ]);
            }

            if ($finalEmployeeId) {
                ApprovalWorkflowAssignee::query()->create([
                    'workflow_configuration_id' => $config->id,
                    'employee_id' => $finalEmployeeId,
                    'approval_type' => ApprovalAssigneeType::FinalApprover,
                    'sequence_order' => 0,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_approval_assignments');

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('central_approval_config_id');
            $table->dropColumn(['central_approval_config_version', 'current_approval_stage']);
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('central_approval_config_id');
            $table->dropColumn('central_approval_config_version');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('central_approval_config_id');
            $table->dropColumn('central_approval_config_version');
        });

        Schema::dropIfExists('approval_workflow_configuration_histories');
        Schema::dropIfExists('approval_workflow_assignees');
        Schema::dropIfExists('approval_workflow_configurations');
    }
};
