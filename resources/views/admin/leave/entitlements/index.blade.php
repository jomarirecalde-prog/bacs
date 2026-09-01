@extends('layouts.app')

@section('title', 'Employee Leave Balances')
@section('page-title', 'Employee Leave Balances')
@section('page-subtitle', 'Individual leave entitlements and balances per employee')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form class="filter-bar flex-1">
            <div class="sm:min-w-[12rem] flex-[2]">
                <label class="label" for="ent-q">Search</label>
                <input id="ent-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Name, ID, department, position">
            </div>
            <div class="sm:min-w-[11rem]">
                <label class="label" for="ent-dept">Department</label>
                <select id="ent-dept" name="department_id" class="select">
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:min-w-[10rem]">
                <label class="label" for="ent-employment">Employment</label>
                <select id="ent-employment" name="employment_status" class="select">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\EmploymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['employment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:min-w-[9rem]">
                <label class="label" for="ent-year">Year</label>
                <input id="ent-year" type="number" name="year" value="{{ $year }}" class="input" min="2020" max="2100">
            </div>
            <button type="submit" class="btn-secondary">Filter</button>
        </form>
        <a href="{{ route('admin.leave.policy') }}" class="btn-outline w-full shrink-0 lg:w-auto">Company default policy</a>
    </div>

    <div class="alert-info">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm">Each employee has independent leave balances. Editing one employee never changes another. Company default policy applies only when initializing new employees.</span>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap table-scroll-mobile">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                        @foreach ($displayTypes as $code)
                            <th class="whitespace-nowrap">{{ \App\Enums\LeaveType::tryFrom($code)?->label() ?? ucfirst($code) }}<br><span class="font-normal text-muted">Ent / Taken / Bal</span></th>
                        @endforeach
                        <th>Last updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $row)
                        @php
                            $employee = $row['employee'];
                            $balances = $row['balances'];
                        @endphp
                        <tr>
                            <td class="font-semibold whitespace-nowrap">{{ $employee->fullName() }}</td>
                            <td class="tabular-nums">{{ $employee->employee_number }}</td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td>{{ $employee->position ?? '—' }}</td>
                            <td>
                                <span class="capitalize">{{ $employee->employment_status?->label() ?? '—' }}</span>
                            </td>
                            @foreach ($displayTypes as $code)
                                @php $b = $balances[$code] ?? null; @endphp
                                <td class="tabular-nums whitespace-nowrap">
                                    @if ($b)
                                        {{ rtrim(rtrim(number_format($b['entitled'], 1), '0'), '.') }}
                                        /
                                        {{ rtrim(rtrim(number_format($b['used'], 1), '0'), '.') }}
                                        /
                                        <span class="font-semibold">{{ rtrim(rtrim(number_format($b['remaining'], 1), '0'), '.') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap text-xs text-muted">{{ $row['last_updated']?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="text-right">
                                <x-action-menu label="Actions" class="sm:hidden">
                                    <a href="{{ route('admin.leave.entitlements.show', ['employee' => $employee, 'year' => $year]) }}" class="dropdown-item">View</a>
                                    <a href="{{ route('admin.leave.entitlements.edit', ['employee' => $employee, 'year' => $year]) }}" class="dropdown-item">Edit</a>
                                </x-action-menu>
                                <div class="action-group hidden justify-end sm:flex">
                                    <a class="btn-outline btn-sm" href="{{ route('admin.leave.entitlements.show', ['employee' => $employee, 'year' => $year]) }}">View</a>
                                    <a class="btn-primary btn-sm" href="{{ route('admin.leave.entitlements.edit', ['employee' => $employee, 'year' => $year]) }}">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="p-0"><x-empty-state title="No employees found" message="Try adjusting your search filters." icon="users" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $employees->links() }}</div>
    </div>
</div>
@endsection
