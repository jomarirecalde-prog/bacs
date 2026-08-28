<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_approval_workflows', 'version')) {
            Schema::table('leave_approval_workflows', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('is_active');
                $table->foreignId('created_by')->nullable()->after('version')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('leave_applications', 'workflow_id')) {
            Schema::table('leave_applications', function (Blueprint $table) {
                $table->foreignId('workflow_id')->nullable()->after('department_id')->constrained('leave_approval_workflows')->nullOnDelete();
                $table->unsignedInteger('workflow_version')->nullable()->after('workflow_id');
            });
        }

        if (! Schema::hasTable('leave_workflow_configuration_histories')) {
            Schema::create('leave_workflow_configuration_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('leave_approval_workflows')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 64);
                $table->json('previous_configuration')->nullable();
                $table->json('new_configuration')->nullable();
                $table->text('summary')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['workflow_id', 'created_at'], 'leave_wf_cfg_hist_wf_created_idx');
                $table->index(['department_id', 'created_at'], 'leave_wf_cfg_hist_dept_created_idx');
            });
        }

        DB::table('leave_approval_workflow_approvers')
            ->where('stage', 'hr_officer')
            ->update(['stage' => 'ceo_final_approval']);

        DB::table('leave_approval_assignments')
            ->where('stage', 'hr_officer')
            ->whereIn('leave_application_id', function ($query) {
                $query->select('id')
                    ->from('leave_applications')
                    ->whereIn('status', [
                        'pending_supervisor',
                        'pending_department_head',
                        'pending_administrative_head',
                        'pending_ceo_final_approval',
                        'partially_approved',
                    ]);
            })
            ->update(['stage' => 'ceo_final_approval']);

        DB::table('leave_applications')
            ->where('current_stage', 'hr_officer')
            ->whereIn('status', [
                'pending_supervisor',
                'pending_department_head',
                'pending_administrative_head',
                'partially_approved',
            ])
            ->update([
                'current_stage' => 'ceo_final_approval',
                'status' => 'pending_ceo_final_approval',
            ]);
    }

    public function down(): void
    {
        DB::table('leave_approval_workflow_approvers')
            ->where('stage', 'ceo_final_approval')
            ->update(['stage' => 'hr_officer']);

        Schema::dropIfExists('leave_workflow_configuration_histories');

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
            $table->dropColumn('workflow_version');
        });

        Schema::table('leave_approval_workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('version');
        });
    }
};
