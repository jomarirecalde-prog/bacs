@extends('layouts.app')

@section('title', 'My Payroll')
@section('page-title', 'My Payroll')
@section('page-subtitle', 'Payslips for finalized pay periods only')

@section('content')
<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Designation</th>
                    <th class="text-right">Net pay</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink">{{ $record->payrollPeriod->period_name }}</div>
                            <div class="text-xs text-muted">{{ $record->payrollPeriod->start_date->format('M j') }} – {{ $record->payrollPeriod->end_date->format('M j, Y') }}</div>
                        </td>
                        <td>{{ $record->designation_name ?? '—' }}</td>
                        <td class="text-right tabular-nums font-bold text-brand-700">₱{{ number_format($record->net_pay, 2) }}</td>
                        <td class="text-right space-x-1">
                            <a class="btn-outline btn-sm" href="{{ route('employee.payroll.show', $record) }}">View</a>
                            <a class="btn-outline btn-sm" href="{{ route('employee.payroll.pdf', $record) }}">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-0"><x-empty-state title="No payslips yet" message="Payslips appear here after HR finalizes payroll for a period." icon="document" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($records->hasPages())
        <div class="border-t border-line p-4">{{ $records->links() }}</div>
    @endif
</div>
@endsection
