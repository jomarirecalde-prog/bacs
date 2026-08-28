@extends('layouts.app')

@section('title', 'Leave Entitlements — '.$employee->fullName())
@section('page-title', 'Leave Entitlements')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="space-y-6">
    @include('admin.leave.entitlements.partials.employee-header', ['employee' => $employee, 'year' => $year])

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">{{ $year }} leave balances</h2>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Leave type</th>
                        <th>Entitled days</th>
                        <th>Leave taken</th>
                        <th>Current balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        @if ($type->code === 'special')
                            @continue
                        @endif
                        @php $b = $balances[$type->code] ?? null; @endphp
                        <tr>
                            <td class="font-semibold">{{ $type->name }}</td>
                            <td class="tabular-nums">{{ $b ? rtrim(rtrim(number_format($b['entitled'], 1), '0'), '.') : '0' }}</td>
                            <td class="tabular-nums">{{ $b ? rtrim(rtrim(number_format($b['used'], 1), '0'), '.') : '0' }}</td>
                            <td class="tabular-nums font-semibold">{{ $b ? rtrim(rtrim(number_format($b['remaining'], 1), '0'), '.') : '0' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.leave.entitlements') }}" class="btn-outline">Back to list</a>
        @can('manage', \App\Models\LeaveBalance::class)
            <a href="{{ route('admin.leave.entitlements.edit', ['employee' => $employee, 'year' => $year]) }}" class="btn-primary">Edit balances</a>
        @endcan
        <a href="{{ route('admin.leave.entitlements.leave-history', $employee) }}" class="btn-secondary">Leave history</a>
        <a href="{{ route('admin.leave.entitlements.adjustments', ['employee' => $employee, 'year' => $year]) }}" class="btn-secondary">Adjustment history</a>
    </div>
</div>
@endsection
