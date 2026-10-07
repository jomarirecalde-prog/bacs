@extends('layouts.app')

@section('title', 'Payroll Adjustments')
@section('page-title', 'Payroll adjustments')
@section('page-subtitle', $period->period_name)

@section('content')
<div class="mb-4 flex gap-2">
    <a href="{{ route('admin.payroll.periods.show', $period) }}" class="btn-outline btn-sm">← Period</a>
    @if (! $period->isLocked())
        <a href="{{ route('admin.payroll.adjustments.create', $period) }}" class="btn-primary btn-sm">New adjustment</a>
    @endif
</div>

<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table text-sm">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Direction</th>
                    <th class="text-right">Amount</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($adjustments as $adj)
                    <tr>
                        <td>{{ $adj->employee?->full_name }}</td>
                        <td>{{ $adj->adjustment_type }}</td>
                        <td>{{ ucfirst($adj->direction) }}</td>
                        <td class="text-right tabular-nums">₱{{ number_format($adj->adjustment_amount, 2) }}</td>
                        <td class="max-w-xs truncate">{{ $adj->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-0"><x-empty-state title="No adjustments" message="Use adjustments for retro pay after finalized periods." icon="document" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
