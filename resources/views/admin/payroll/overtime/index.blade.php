@extends('layouts.app')

@section('title', 'Overtime Approval')
@section('page-title', 'Overtime approval')
@section('page-subtitle', $period->period_name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.payroll.periods.show', $period) }}" class="btn-outline btn-sm">← Period</a>
</div>

<div class="alert-info mb-4 text-sm">
    This list refreshes from <strong>DTR clock OT</strong> and <strong>OT Attachment Report activities</strong> (declared time from/to) each time you open it
    @if (($imported ?? 0) > 0)
        — <strong>{{ $imported }}</strong> row(s) synced just now.
    @endif
    Employees file <strong>OT Attachment Reports</strong> against these payroll OT rows; review reports here (no separate endorsement workflow).
    Set <code>PAYROLL_OT_REQUIRES_APPROVAL=true</code> in `.env` to pay only approved OT hours.
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
                    <th>OT status</th>
                    <th>Attachment</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $ot)
                    @php
                        $attachment = $ot->attachmentReport;
                        $fmt = fn (int $mins) => sprintf('%d:%02d', intdiv($mins, 60), $mins % 60);
                    @endphp
                    <tr>
                        <td>{{ $ot->employee?->full_name }} <span class="text-muted">{{ $ot->employee?->employee_number }}</span></td>
                        <td>{{ $ot->attendance_date->format('M j, Y') }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($ot->recorded_minutes) }}</td>
                        <td class="text-right tabular-nums">{{ $fmt($ot->approved_minutes) }}</td>
                        <td>{{ $ot->status?->label() }}</td>
                        <td>
                            @if ($attachment)
                                <a class="font-semibold text-brand-700" href="{{ route('admin.payroll.overtime.attachment.pdf', [$period, $attachment]) }}" target="_blank" rel="noopener">{{ $attachment->report_no }}</a>
                                <span class="ml-1 {{ $attachment->status->badgeClass() }}">{{ $attachment->status->label() }}</span>
                                @if ($attachment->total_activity_minutes > 0)
                                    <span class="ml-1 text-muted tabular-nums">· {{ $attachment->durationLabel() }} reported</span>
                                @endif
                            @else
                                <span class="text-muted">Not submitted</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                @if ($ot->status === \App\Enums\OvertimeRequestStatus::Pending)
                                    <form method="POST" action="{{ route('admin.payroll.overtime.decide', $ot) }}" class="inline-flex">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <input type="hidden" name="approved_minutes" value="{{ $ot->recorded_minutes }}">
                                        <button type="submit" class="btn-primary btn-sm">Approve OT</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payroll.overtime.decide', $ot) }}" class="inline-flex">
                                        @csrf
                                        <input type="hidden" name="decision" value="deny">
                                        <button type="submit" class="btn-outline-danger btn-sm">Deny OT</button>
                                    </form>
                                @endif
                                @if ($attachment && $attachment->pendingPayrollReview())
                                    <form method="POST" action="{{ route('admin.payroll.overtime.attachment.decide', [$period, $ot]) }}" class="inline-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="btn-outline btn-sm">Approve report</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payroll.overtime.attachment.decide', [$period, $ot]) }}" class="inline-flex">
                                        @csrf
                                        <input type="hidden" name="decision" value="return">
                                        <input type="text" name="review_notes" class="input input-sm max-w-[8rem]" placeholder="Return notes" required>
                                        <button type="submit" class="btn-outline btn-sm">Return</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.payroll.overtime.attachment.decide', [$period, $ot]) }}" class="inline-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="text" name="review_notes" class="input input-sm max-w-[8rem]" placeholder="Reject reason" required>
                                        <button type="submit" class="btn-outline-danger btn-sm">Reject report</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0"><x-empty-state title="No overtime rows" message="There is no recorded OT for this period yet. Employees need a submitted Official Time request and/or OT minutes on the DTR for these dates. Reload after they apply, or run Compute Attendance on the payroll period." icon="clock" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="border-t border-line p-4">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
