@extends('layouts.app')

@section('title', $department->name.' — Leave Approval Configuration')
@section('page-title', $department->name)
@section('page-subtitle', 'Department leave approval workflow configuration')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.leave.workflow') }}" class="btn-outline btn-sm">All departments</a>
        <a href="{{ route('admin.leave.workflow.history', $department) }}" class="btn-ghost btn-sm">Workflow history</a>
        @if ($workflow->is_active)
            <form method="POST" action="{{ route('admin.leave.workflow.deactivate', $department) }}" class="inline">
                @csrf
                <button type="submit" class="btn-outline btn-sm">Deactivate</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.leave.workflow.activate', $department) }}" class="inline">
                @csrf
                <button type="submit" class="btn-primary btn-sm" @disabled(count($missing) > 0)>Activate</button>
            </form>
        @endif
    </div>

    @if (count($missing) > 0)
        <div class="alert-warn">
            <span class="text-sm">Cannot activate this workflow until all required stages are assigned. Missing: <strong>{{ implode(', ', $missing) }}</strong>.</span>
        </div>
    @endif

    @error('activate')
        <div class="alert-critical"><span class="text-sm">{{ $message }}</span></div>
    @enderror

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card card-accent-brand xl:col-span-1">
            <div class="card-header"><h2 class="card-title">Department Information</h2></div>
            <div class="card-body space-y-3 text-sm">
                <div><span class="text-muted">Department</span><div class="font-semibold">{{ $department->name }}</div></div>
                <div><span class="text-muted">Employees</span><div class="font-semibold">{{ $department->employees_count }}</div></div>
                <div>
                    <span class="text-muted">Configuration Status</span>
                    <div>
                        @if ($workflow->is_active && count($missing) === 0)
                            <span class="badge-brand">Active</span>
                        @elseif (count($missing) > 0)
                            <span class="badge-warn">Incomplete</span>
                        @else
                            <span class="badge-neutral">Inactive</span>
                        @endif
                    </div>
                </div>
                <div><span class="text-muted">Version</span><div class="font-semibold tabular-nums">{{ $workflow->version }}</div></div>
                <div>
                    <span class="text-muted">Last Updated</span>
                    <div class="font-semibold">{{ $workflow->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</div>
                    @if ($workflow->updatedByUser)
                        <div class="text-xs text-muted">by {{ $workflow->updatedByUser->name }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Approval Workflow</h2></div>
            <div class="card-body">
                @include('admin.leave.configuration.partials.workflow-visual')
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.leave.workflow.update', $department) }}" class="card card-accent-gold">
        @csrf
        @method('PUT')
        <div class="card-header">
            <div>
                <h2 class="card-title">Approval Workflow Builder</h2>
                <p class="text-xs text-muted">Assign authorized personnel for each stage. CEO final approval is system-designated and cannot be changed here.</p>
            </div>
        </div>
        <div class="card-body space-y-6">
            <div>
                <label class="label">Parallel approval rule (Immediate Supervisor/Superior)</label>
                <select name="parallel_rule" class="select max-w-xl">
                    @foreach (\App\Enums\LeaveParallelRule::cases() as $rule)
                        <option value="{{ $rule->value }}" @selected(old('parallel_rule', $workflow->parallel_rule?->value) === $rule->value)>{{ $rule->label() }}</option>
                    @endforeach
                </select>
                <p class="hint">{{ $parallelLabel }}</p>
            </div>

            @include('admin.leave.configuration.partials.approver-picker', [
                'stage' => 'immediate_supervisor',
                'label' => 'Immediate Supervisor / Superior',
                'multiple' => true,
                'selected' => old('approvers.immediate_supervisor') ? collect(old('approvers.immediate_supervisor'))->map(fn ($id) => ['id' => (int) $id, 'name' => 'Selected user #'.$id])->all() : ($selected['immediate_supervisor'] ?? []),
                'name' => 'approvers[immediate_supervisor]',
            ])

            @include('admin.leave.configuration.partials.approver-picker', [
                'stage' => 'department_head',
                'label' => 'Department Head',
                'multiple' => false,
                'selected' => old('approvers.department_head') ? collect(old('approvers.department_head'))->map(fn ($id) => ['id' => (int) $id, 'name' => 'Selected user #'.$id])->all() : ($selected['department_head'] ?? []),
                'name' => 'approvers[department_head]',
            ])

            @include('admin.leave.configuration.partials.approver-picker', [
                'stage' => 'administrative_head',
                'label' => 'Administrative Head',
                'multiple' => false,
                'selected' => old('approvers.administrative_head') ? collect(old('approvers.administrative_head'))->map(fn ($id) => ['id' => (int) $id, 'name' => 'Selected user #'.$id])->all() : ($selected['administrative_head'] ?? []),
                'name' => 'approvers[administrative_head]',
            ])

            <div class="rounded-xl border border-gold-200 bg-gold-50/60 p-4">
                <div class="text-sm font-bold text-gold-900">CEO — Final Approval</div>
                <div class="mt-1 text-sm text-ink">{{ $ceoLabel }}</div>
                <p class="mt-2 text-xs text-muted">The CEO is always the final approver. Designate the CEO account through organizational settings or employee position (CEO / President).</p>
                @unless ($ceoUser)
                    <p class="mt-2 text-xs font-semibold text-critical-700">No CEO account is currently designated.</p>
                @endunless
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-primary">Save configuration</button>
                <button type="submit" name="activate" value="1" class="btn-secondary" @disabled(count($missing) > 0 && ! $workflow->is_active)>Save &amp; Activate</button>
            </div>
        </div>
    </form>
</div>
@endsection
