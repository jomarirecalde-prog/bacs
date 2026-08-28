@extends('layouts.app')

@section('title', 'Leave Approval Configuration')
@section('page-title', 'Leave Approval Configuration')
@section('page-subtitle', 'Department hierarchy and parallel supervisor rules')

@section('content')
<form method="POST" action="{{ route('admin.leave.workflow.update') }}" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="alert-info">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm">Immediate Supervisor/Superior approvers receive each request at the same time. Choose whether all, any one, or a majority must approve that parallel stage. Empty stages are skipped. Approvers cannot act on their own leave.</span>
    </div>

    @foreach ($workflows as $index => $workflow)
        <div class="card {{ $workflow->is_default ? 'card-accent-gold' : 'card-accent-brand' }}">
            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ $workflow->name }}</h2>
                    <p class="text-xs text-muted">{{ $workflow->is_default ? 'Company default — used when a department has no override' : ($workflow->department?->name ?? 'Department workflow') }}</p>
                </div>
            </div>
            <div class="card-body space-y-4">
                <input type="hidden" name="workflows[{{ $index }}][id]" value="{{ $workflow->id }}">
                <div>
                    <label class="label">Parallel approval rule (Immediate Supervisor/Superior)</label>
                    <select name="workflows[{{ $index }}][parallel_rule]" class="select max-w-xl">
                        @foreach (\App\Enums\LeaveParallelRule::cases() as $rule)
                            <option value="{{ $rule->value }}" @selected($workflow->parallel_rule === $rule)>{{ $rule->label() }}</option>
                        @endforeach
                    </select>
                </div>

                @foreach ($stages as $stage)
                    @php $selected = $workflow->approvers->where('stage', $stage)->pluck('user_id')->map(fn ($id) => (string) $id)->all(); @endphp
                    <div>
                        <label class="label">{{ $stage->label() }}{{ $stage->isParallel() ? ' (multiple, parallel)' : '' }}</label>
                        <select name="workflows[{{ $index }}][approvers][{{ $stage->value }}][]" class="select min-h-32" multiple>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(in_array((string) $user->id, $selected, true))>
                                    {{ $user->employee?->fullName() ?: $user->name }}
                                    @if ($user->employee?->position) — {{ $user->employee->position }} @endif
                                    @if ($user->employee?->department) ({{ $user->employee->department->name }}) @endif
                                    · {{ $user->role?->label() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="hint">Hold Ctrl (Windows) or Cmd (Mac) to select multiple people.</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <button class="btn-primary" type="submit">Save approval configuration</button>
</form>
@endsection
