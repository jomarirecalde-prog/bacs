@extends('layouts.app')

@section('title', 'My Leave Balances')
@section('page-title', 'My Leave Balances')
@section('page-subtitle', 'View-only summary of your leave credits')

@section('content')
<div class="space-y-6">
    <div class="card card-accent-brand">
        <div class="card-body grid gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted">Employee</p>
                <p class="font-semibold">{{ $employee->fullName() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-muted">Department</p>
                <p class="font-semibold">{{ $employee->department?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    <form class="filter-bar">
        <div class="min-w-[9rem]">
            <label class="label" for="bal-year">Year</label>
            <input id="bal-year" type="number" name="year" value="{{ $year }}" class="input" min="2020" max="2100">
        </div>
        <button type="submit" class="btn-secondary">Update</button>
    </form>

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
        <a href="{{ route('employee.leave.balances.adjustments', ['year' => $year]) }}" class="btn-secondary">Adjustment history</a>
        <a href="{{ route('employee.leave.index') }}" class="btn-outline">My leave applications</a>
    </div>
</div>
@endsection
