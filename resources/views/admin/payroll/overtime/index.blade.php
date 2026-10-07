@extends('layouts.app')

@section('title', 'Overtime Approval')
@section('page-title', 'Overtime approval')
@section('page-subtitle', $period->period_name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.payroll.periods.show', $period) }}" class="btn-outline btn-sm">← Period</a>
</div>

<div class="alert-info mb-4 text-sm">
    OT rows sync from DTR when you compute attendance. Set <code>PAYROLL_OT_REQUIRES_APPROVAL=true</code> in `.env` to pay only approved OT hours.
</div>

<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table text-sm">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th class="text-right">Recorded</th>
                    <th class="text-right">Approved</th>
                    <th>Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $ot)
                    <tr>
                        <td>{{ $ot->employee?->full_name }} <span class="text-muted">{{ $ot->employee?->employee_number }}</span></td>
                        <td>{{ $ot->attendance_date->format('M j, Y') }}</td>
                        <td class="text-right tabular-nums">{{ sprintf('%d:%02d', intdiv($ot->recorded_minutes, 60), $ot->recorded_minutes % 60) }}</td>
                        <td class="text-right tabular-nums">{{ sprintf('%d:%02d', intdiv($ot->approved_minutes, 60), $ot->approved_minutes % 60) }}</td>
                        <td>{{ $ot->status?->label() }}</td>
                        <td class="text-right">
                            @if ($ot->status === \App\Enums\OvertimeRequestStatus::Pending)
                                <form method="POST" action="{{ route('admin.payroll.overtime.decide', $ot) }}" class="inline-flex gap-1">
                                    @csrf
                                    <input type="hidden" name="decision" value="approve">
                                    <input type="hidden" name="approved_minutes" value="{{ $ot->recorded_minutes }}">
                                    <button type="submit" class="btn-primary btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payroll.overtime.decide', $ot) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="decision" value="deny">
                                    <button type="submit" class="btn-outline-danger btn-sm">Deny</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-empty-state title="No overtime rows" message="Compute attendance to import OT from DTR." icon="clock" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="border-t border-line p-4">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
