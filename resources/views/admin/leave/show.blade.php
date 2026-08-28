@extends('layouts.app')

@section('title', $application->application_number)
@section('page-title', $application->application_number)
@section('page-subtitle', $application->employee?->fullName().' · '.$application->status->label())

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.leave.index') }}" class="btn-outline btn-sm">Back to applications</a>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary btn-sm" href="{{ route('admin.leave.pdf', $application) }}">Download PDF</a>
            <a class="btn-outline btn-sm" href="{{ route('admin.leave.print', $application) }}" target="_blank">Print official form</a>
        </div>
    </div>

    @if ($application->attendance_conflict)
        <div class="alert-warning">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
            <span>Time entries exist on one or more approved leave dates. Existing DTR punches were preserved for HR review.</span>
        </div>
        @if ($application->conflicts->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title">Attendance conflicts</h3></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($application->conflicts as $conflict)
                                <tr>
                                    <td>{{ $conflict->attendance_date?->format('M j, Y') }}</td>
                                    <td>{{ $conflict->time_in?->format('h:i A') ?? '—' }}</td>
                                    <td>{{ $conflict->time_out?->format('h:i A') ?? '—' }}</td>
                                    <td>{{ $conflict->attendance_status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 overflow-auto rounded-2xl border border-line bg-white p-3 shadow-soft">
            @include('leave.partials.official-form-css')
            @include('leave.partials.official-form')
        </div>
        <div class="space-y-4">
            <div class="card card-accent-gold">
                <div class="card-header"><h3 class="card-title">Approval workflow</h3></div>
                <div class="card-body">@include('leave.partials.timeline')</div>
            </div>

            @if ($canApprove)
                <div class="card card-accent-brand" x-data="{ decision: 'approved' }">
                    <div class="card-header"><h3 class="card-title">Your decision</h3></div>
                    <form method="POST" action="{{ route('leave.approvals.decide', $application) }}" class="card-body space-y-4">
                        @csrf
                        <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" class="radio" x-model="decision"> APPROVED</label>
                        <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied" class="radio" x-model="decision"> DENIED</label>
                        <textarea name="reason" rows="3" class="textarea" :required="decision === 'denied'" placeholder="Reason"></textarea>
                        <button class="btn-primary btn-block" type="submit">Record decision</button>
                    </form>
                </div>
            @endif

            @if ($canProcessHr)
                @include('leave.partials.hr-form')
            @endif

            <div class="card">
                <div class="card-header"><h3 class="card-title">Audit trail</h3></div>
                <div class="card-body space-y-3">
                    @forelse ($application->actions as $action)
                        <div class="border-b border-line pb-3 last:border-0 last:pb-0">
                            <div class="text-sm font-semibold text-ink">{{ $action->user?->name }} · {{ str_replace('_', ' ', $action->action) }}</div>
                            <div class="text-xs text-muted">{{ $action->actedAtLabel() }} · {{ $action->previous_status?->label() }} → {{ $action->new_status?->label() }}</div>
                            @if ($action->reason)
                                <div class="mt-1 text-xs">{{ $action->reason }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-muted">No audit entries yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
