@extends('layouts.app')

@php
    use App\Enums\EmployeeSalaryStatus;
@endphp

@section('title', 'Salary History')
@section('page-title', 'Salary history')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="page-stack">
    <div class="page-header">
        <nav class="breadcrumb min-w-0" aria-label="Breadcrumb">
            <a href="{{ route('admin.employees.index') }}" class="truncate transition hover:text-brand-700">Employees</a>
            <span class="text-faint" aria-hidden="true">/</span>
            <a href="{{ route('admin.employees.show', $employee) }}" class="truncate transition hover:text-brand-700">{{ $employee->employee_number }}</a>
            <span class="text-faint" aria-hidden="true">/</span>
            <span class="truncate text-ink-soft">Salary</span>
        </nav>
        <div class="action-group shrink-0">
            <a href="{{ route('admin.employees.show', $employee) }}" class="btn-outline btn-sm">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Employee profile
            </a>
        </div>
    </div>

    <div class="employee-context-bar">
        <div class="flex min-w-0 items-center gap-4">
            <img src="{{ $employee->photoUrl() }}" alt="" loading="lazy" decoding="async" class="h-14 w-14 shrink-0 rounded-xl object-cover ring-2 ring-brand-100 sm:h-16 sm:w-16">
            <div class="min-w-0">
                <h2 class="truncate text-base font-extrabold tracking-tight text-ink sm:text-lg">{{ $employee->fullName() }}</h2>
                <p class="mt-0.5 truncate text-sm text-muted">
                    {{ $employee->employee_number }}
                    @if ($employee->department)
                        · {{ $employee->department->name }}
                    @endif
                    @if ($employee->designation)
                        · {{ $employee->designation->designation_name }}
                    @endif
                </p>
                @if ($current)
                    <p class="mt-2 text-xs text-muted">
                        Active rate since <span class="font-semibold text-ink-soft">{{ $current->effective_from->format('M j, Y') }}</span>
                    </p>
                @else
                    <p class="mt-2 text-xs font-medium text-warn-800">No active salary assignment — add one below to include this employee in payroll.</p>
                @endif
            </div>
        </div>
        @if ($current)
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <span class="badge-gold">{{ $current->salary_type?->label() }}</span>
                @if ($current->status === EmployeeSalaryStatus::Active)
                    <span class="badge-brand">Active</span>
                @endif
            </div>
        @endif
    </div>

    @if ($current)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Monthly equivalent" :value="'₱'.number_format((float) ($current->monthly_salary ?? 0), 2)" tone="gold" icon="chart" />
            <x-stat-card label="Semi-monthly" :value="$current->semi_monthly_salary ? '₱'.number_format((float) $current->semi_monthly_salary, 2) : '—'" tone="info" icon="document" />
            <x-stat-card label="Daily rate" :value="$current->daily_rate ? '₱'.number_format((float) $current->daily_rate, 2) : '—'" tone="brand" icon="clock" />
            <x-stat-card label="Hourly rate" :value="$current->hourly_rate ? '₱'.number_format((float) $current->hourly_rate, 2) : '—'" tone="blue" icon="clock" />
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="card card-accent-brand overflow-hidden xl:col-span-5">
            <div class="card-header">
                <div>
                    <h2 class="card-title">New assignment</h2>
                    <p class="mt-0.5 text-xs text-muted">Creates a dated salary record and closes the previous active assignment.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.payroll.employees.salary.store', $employee) }}" class="form-card-body"
                x-data="{ submitting: false }"
                @submit="submitting = true"
                @if ($designationDefaults) data-designation-defaults='@json($designationDefaults)' @endif>
                @csrf
                @if ($employee->designation)
                    <div class="alert-info">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs sm:text-sm">
                            Defaults from
                            <a class="font-semibold text-info-800 underline-offset-2 hover:underline" href="{{ route('admin.designations.show', $employee->designation) }}">{{ $employee->designation->designation_name }}</a>
                            — adjust amounts before saving.
                        </span>
                    </div>
                @endif
                <div>
                    <label class="label" for="salary_type">Salary type</label>
                    <select id="salary_type" class="select @error('salary_type') input-error @enderror" name="salary_type" required>
                        @foreach (\App\Enums\SalaryType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('salary_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('salary_type')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="amount">Amount (₱)</label>
                    <input id="amount" class="input @error('amount') input-error @enderror" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required inputmode="decimal">
                    <p class="hint">Primary rate for the selected type (monthly, daily, hourly, etc.).</p>
                    @error('amount')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="designation_id">Designation snapshot</label>
                    <select id="designation_id" class="select" name="designation_id">
                        <option value="">Use employee current designation</option>
                        @if ($employee->designation)
                            <option value="{{ $employee->designation_id }}" @selected(old('designation_id', $employee->designation_id) == $employee->designation_id)>{{ $employee->designation->designation_name }}</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="label" for="effective_from">Effective from</label>
                    <input id="effective_from" type="date" class="input @error('effective_from') input-error @enderror" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}" required>
                    @error('effective_from')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" class="textarea @error('notes') input-error @enderror" name="notes" rows="2" placeholder="Optional context for HR or audit">{{ old('notes') }}</textarea>
                    @error('notes')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary btn-block" :disabled="submitting">
                    <span x-show="!submitting">Save assignment</span>
                    <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                        <span class="spinner" aria-hidden="true"></span>
                        Saving…
                    </span>
                </button>
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

        <div class="space-y-6 xl:col-span-7">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="card card-accent-gold overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">De minimis</h2>
                            <p class="mt-0.5 text-xs text-muted">Non-taxable benefit per cut-off.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.payroll.employees.benefits.store', $employee) }}" class="form-card-body space-y-3"
                        x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <div>
                            <label class="label" for="benefit_amount">Amount per cut-off (₱)</label>
                            <input id="benefit_amount" class="input @error('amount') input-error @enderror" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" placeholder="0.00" required inputmode="decimal">
                            @error('amount')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="benefit_effective_from">Effective from</label>
                            <input id="benefit_effective_from" class="input" type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}">
                        </div>
                        <button type="submit" class="btn-secondary btn-sm btn-block" :disabled="submitting">
                            <span x-show="!submitting">Add / update de minimis</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2"><span class="spinner"></span> Saving…</span>
                        </button>
                    </form>
                    <div class="border-t border-line px-4 pb-4 sm:px-5">
                        <p class="pt-3 text-[10px] font-bold uppercase tracking-widest text-muted">Configured</p>
                        @if ($benefits->isEmpty())
                            <p class="py-3 text-sm text-muted">None configured yet.</p>
                        @else
                            <ul class="compact-list text-sm">
                                @foreach ($benefits as $benefit)
                                    <li class="compact-list-item">
                                        <span class="font-semibold text-ink tabular-nums">₱{{ number_format($benefit->amount, 2) }}</span>
                                        <span class="text-muted">From {{ $benefit->effective_from?->format('M j, Y') ?? '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="card card-accent-info overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Recurring deduction</h2>
                            <p class="mt-0.5 text-xs text-muted">Applied each payroll cut-off.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.payroll.employees.deductions.store', $employee) }}" class="form-card-body space-y-3"
                        x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <div>
                            <label class="label" for="deduction_type_id">Deduction type</label>
                            <select id="deduction_type_id" class="select @error('deduction_type_id') input-error @enderror" name="deduction_type_id" required>
                                <option value="">Select type</option>
                                @foreach ($deductionTypes as $type)
                                    <option value="{{ $type->id }}" @selected(old('deduction_type_id') == $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('deduction_type_id')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="deduction_amount">Amount per cut-off (₱)</label>
                            <input id="deduction_amount" class="input @error('amount') input-error @enderror" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" placeholder="0.00" required inputmode="decimal">
                        </div>
                        <div>
                            <label class="label" for="deduction_effective_from">Effective from</label>
                            <input id="deduction_effective_from" class="input" type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}">
                        </div>
                        <button type="submit" class="btn-secondary btn-sm btn-block" :disabled="submitting">
                            <span x-show="!submitting">Add deduction</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2"><span class="spinner"></span> Saving…</span>
                        </button>
                    </form>
                    <div class="border-t border-line px-4 pb-4 sm:px-5">
                        <p class="pt-3 text-[10px] font-bold uppercase tracking-widest text-muted">Recent</p>
                        @if ($recurringDeductions->isEmpty())
                            <p class="py-3 text-sm text-muted">No recurring deductions on file.</p>
                        @else
                            <ul class="compact-list text-sm">
                                @foreach ($recurringDeductions as $deduction)
                                    <li class="compact-list-item">
                                        <span class="font-semibold text-ink">{{ $deduction->deductionType?->name ?? 'Deduction' }}</span>
                                        <span class="text-muted tabular-nums">₱{{ number_format($deduction->amount, 2) }} · {{ $deduction->effective_from?->format('M j, Y') ?? '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header">
                    <div class="min-w-0">
                        <h2 class="card-title">Assignment history</h2>
                        <p class="mt-0.5 text-xs text-muted">All salary records for this employee, newest first.</p>
                    </div>
                    @if ($current)
                        <span class="chip max-w-full truncate">
                            Current: {{ $current->salary_type?->label() }} · {{ $current->effective_from->format('M j, Y') }}
                        </span>
                    @endif
                </div>
                <div class="table-wrap">
                    <table class="data-table text-sm">
                        <thead>
                            <tr>
                                <th>Effective</th>
                                <th>Type</th>
                                <th class="hidden md:table-cell">Designation</th>
                                <th class="text-right">Monthly</th>
                                <th class="text-right hidden sm:table-cell">Daily</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $row)
                                @php
                                    $statusBadge = match ($row->status) {
                                        EmployeeSalaryStatus::Active => 'badge-brand',
                                        EmployeeSalaryStatus::Superseded => 'badge-neutral',
                                        EmployeeSalaryStatus::Cancelled => 'badge-critical',
                                        default => 'badge-neutral',
                                    };
                                    $rowClass = $row->status === EmployeeSalaryStatus::Active ? 'row-featured' : '';
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>
                                        {{ $row->effective_from->format('M j, Y') }}
                                        @if ($row->effective_to)
                                            <span class="text-muted">– {{ $row->effective_to->format('M j, Y') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->salary_type?->label() }}</td>
                                    <td class="hidden md:table-cell">{{ $row->designation?->designation_name ?? '—' }}</td>
                                    <td class="text-right tabular-nums font-medium text-ink">{{ $row->monthly_salary ? '₱'.number_format($row->monthly_salary, 2) : '—' }}</td>
                                    <td class="text-right tabular-nums hidden sm:table-cell">{{ $row->daily_rate ? '₱'.number_format($row->daily_rate, 2) : '—' }}</td>
                                    <td>
                                        <span class="{{ $statusBadge }}">{{ $row->status?->label() ?? '—' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-0"><x-empty-state title="No salary records" message="Add a salary assignment for this employee." icon="document" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($history->hasPages())
                    <div class="card-footer">{{ $history->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
