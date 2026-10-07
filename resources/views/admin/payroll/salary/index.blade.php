@extends('layouts.app')

@section('title', 'Salary History')
@section('page-title', 'Salary history')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.employees.show', $employee) }}" class="btn-outline btn-sm">← Back to employee</a>
</div>

<div class="grid gap-6 xl:grid-cols-4">
    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header"><h2 class="card-title">New assignment</h2></div>
        <form method="POST" action="{{ route('admin.payroll.employees.salary.store', $employee) }}" class="space-y-4 p-5"
            @if ($designationDefaults) data-designation-defaults='@json($designationDefaults)' @endif>
            @csrf
            @if ($employee->designation)
                <p class="text-xs text-muted">
                    Defaults from
                    <a class="font-semibold text-brand-700 hover:underline" href="{{ route('admin.designations.show', $employee->designation) }}">{{ $employee->designation->designation_name }}</a>
                    — adjust amounts before saving.
                </p>
            @endif
            <div>
                <label class="label" for="salary_type">Salary type</label>
                <select id="salary_type" class="select" name="salary_type" required>
                    @foreach (\App\Enums\SalaryType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('salary_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="amount">Amount (₱)</label>
                <input id="amount" class="input" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required>
                <p class="hint mt-1">Primary rate for the selected type (monthly, daily, hourly, etc.).</p>
            </div>
            <div>
                <label class="label" for="designation_id">Designation snapshot</label>
                <select id="designation_id" class="select" name="designation_id">
                    <option value="">Use employee current designation</option>
                    @if ($employee->designation)
                        <option value="{{ $employee->designation_id }}" selected>{{ $employee->designation->designation_name }}</option>
                    @endif
                </select>
            </div>
            <div>
                <label class="label" for="effective_from">Effective from</label>
                <input id="effective_from" type="date" class="input" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>
            </div>
            <div>
                <label class="label" for="notes">Notes</label>
                <textarea id="notes" class="textarea" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="btn-primary btn-block">Save assignment</button>
        </form>
        @if ($designationDefaults)
            <script>
                (function () {
                    const form = document.querySelector('[data-designation-defaults]');
                    if (!form || form.querySelector('#amount').value) return;
                    const defaults = JSON.parse(form.dataset.designationDefaults || '{}');
                    const typeEl = form.querySelector('#salary_type');
                    const amountEl = form.querySelector('#amount');
                    const apply = () => {
                        if (defaults.pay_type) typeEl.value = defaults.pay_type;
                        const map = {
                            monthly: defaults.basic_salary,
                            semi_monthly: defaults.semi_monthly_salary,
                            daily: defaults.daily_rate,
                            hourly: defaults.hourly_rate,
                            fixed_period: defaults.basic_salary,
                        };
                        const val = map[typeEl.value];
                        if (val != null && val !== '') amountEl.value = val;
                    };
                    typeEl.addEventListener('change', apply);
                    apply();
                })();
            </script>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">De minimis</h2></div>
        <form method="POST" action="{{ route('admin.payroll.employees.benefits.store', $employee) }}" class="space-y-3 p-5">
            @csrf
            <input class="input" type="number" step="0.01" min="0" name="amount" placeholder="Amount per cut-off" required>
            <input class="input" type="date" name="effective_from" value="{{ now()->toDateString() }}">
            <button type="submit" class="btn-secondary btn-sm btn-block">Add / update de minimis</button>
        </form>
        <ul class="border-t border-line px-5 pb-5 text-sm">
            @forelse ($benefits as $benefit)
                <li class="py-2">₱{{ number_format($benefit->amount, 2) }} · from {{ $benefit->effective_from?->format('M j, Y') ?? '—' }}</li>
            @empty
                <li class="py-2 text-muted">None configured.</li>
            @endforelse
        </ul>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Recurring deduction</h2></div>
        <form method="POST" action="{{ route('admin.payroll.employees.deductions.store', $employee) }}" class="space-y-3 p-5">
            @csrf
            <select class="select" name="deduction_type_id" required>
                <option value="">Type</option>
                @foreach ($deductionTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            <input class="input" type="number" step="0.01" min="0" name="amount" placeholder="Amount per cut-off" required>
            <input class="input" type="date" name="effective_from" value="{{ now()->toDateString() }}">
            <button type="submit" class="btn-secondary btn-sm btn-block">Add deduction</button>
        </form>
    </div>

    <div class="card xl:col-span-2 overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">History</h2>
            @if ($current)
                <span class="chip">Current: {{ $current->salary_type?->label() }} · effective {{ $current->effective_from->format('M j, Y') }}</span>
            @endif
        </div>
        <div class="table-wrap">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Effective</th>
                        <th>Type</th>
                        <th>Designation</th>
                        <th class="text-right">Monthly</th>
                        <th class="text-right">Daily</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr>
                            <td>{{ $row->effective_from->format('M j, Y') }}@if($row->effective_to) – {{ $row->effective_to->format('M j, Y') }}@endif</td>
                            <td>{{ $row->salary_type?->label() }}</td>
                            <td>{{ $row->designation?->designation_name ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $row->monthly_salary ? number_format($row->monthly_salary, 2) : '—' }}</td>
                            <td class="text-right tabular-nums">{{ $row->daily_rate ? number_format($row->daily_rate, 2) : '—' }}</td>
                            <td>{{ $row->status?->label() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-0"><x-empty-state title="No salary records" message="Add a salary assignment for this employee." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($history->hasPages())
            <div class="border-t border-line p-4">{{ $history->links() }}</div>
        @endif
    </div>
</div>
@endsection
