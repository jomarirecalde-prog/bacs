@extends('layouts.app')

@php
    use App\Enums\EmployeeSalaryStatus;

    $deMinimisPerCutoff = (float) ($activeBenefit?->amount ?? 0);
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
        @php
            $salaryAmounts = app(\App\Services\Payroll\EmployeeSalaryService::class);
            $currentBasic = (float) ($salaryAmounts->basicSalaryPerCutoff($current) ?? 0);
            $currentGross = (float) ($salaryAmounts->grossCompensationPerCutoff($current, $deMinimisPerCutoff) ?? ($currentBasic + $deMinimisPerCutoff));
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-stat-card label="Basic salary (per cut-off)" :value="'₱'.number_format($currentBasic, 2)" tone="gold" icon="chart" />
            <x-stat-card label="De minimis (per cut-off)" :value="$deMinimisPerCutoff > 0 ? '₱'.number_format($deMinimisPerCutoff, 2) : '—'" tone="info" icon="document" />
            <x-stat-card label="Gross compensation (declared)" :value="'₱'.number_format($currentGross, 2)" tone="brand" icon="chart" />
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
                x-data="{
                    submitting: false,
                    deMinimis: {{ json_encode($deMinimisPerCutoff) }},
                    suggestGross() {
                        const basic = parseFloat(this.$refs.basicSalary?.value);
                        if (!Number.isFinite(basic) || basic < 0) return;
                        if (this.$refs.grossComp?.dataset.userEdited === '1') return;
                        this.$refs.grossComp.value = (basic + this.deMinimis).toFixed(2);
                    },
                    markGrossEdited() { if (this.$refs.grossComp) this.$refs.grossComp.dataset.userEdited = '1'; }
                }"
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
                    <label class="label" for="basic_salary">Basic salary (₱)</label>
                    <input id="basic_salary" x-ref="basicSalary" class="input @error('basic_salary') input-error @enderror" type="number" step="0.01" min="0" name="basic_salary" value="{{ old('basic_salary') }}" required inputmode="decimal" @input="suggestGross()" @change="suggestGross()">
                    <p class="hint">Cut-off basic pay (semi-monthly amount when pay type is semi-monthly).</p>
                    @error('basic_salary')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="gross_compensation">Gross compensation (₱)</label>
                    <input id="gross_compensation" x-ref="grossComp" class="input @error('gross_compensation') input-error @enderror" type="number" step="0.01" min="0" name="gross_compensation" value="{{ old('gross_compensation') }}" required inputmode="decimal" data-user-edited="0" @input="markGrossEdited()">
                    <p class="hint">Manual entry per cut-off. Typical formula: <span class="font-semibold text-ink-soft">Basic salary + De minimis</span>@if ($deMinimisPerCutoff > 0) (de minimis ₱{{ number_format($deMinimisPerCutoff, 2) }})@endif.</p>
                    @error('gross_compensation')<p class="error-text">{{ $message }}</p>@enderror
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
                        const basicEl = form?.querySelector('#basic_salary');
                        if (!form || !basicEl || basicEl.value) return;
                        const defaults = JSON.parse(form.dataset.designationDefaults || '{}');
                        const typeEl = form.querySelector('#salary_type');
                        const grossEl = form.querySelector('#gross_compensation');
                        const deMinimis = {{ json_encode($deMinimisPerCutoff) }};
                        const apply = () => {
                            if (defaults.pay_type) typeEl.value = defaults.pay_type;
                            const map = {
                                monthly: defaults.monthly_salary ?? defaults.basic_salary,
                                semi_monthly: defaults.semi_monthly_salary ?? defaults.basic_salary,
                                daily: defaults.daily_rate,
                                hourly: defaults.hourly_rate,
                                fixed_period: defaults.basic_salary,
                            };
                            const val = map[typeEl.value];
                            if (val != null && val !== '') {
                                basicEl.value = val;
                                if (grossEl && grossEl.dataset.userEdited !== '1') {
                                    grossEl.value = (parseFloat(val) + deMinimis).toFixed(2);
                                }
                            }
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
                            <input id="benefit_amount" class="input @error('amount') input-error @enderror" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $activeBenefit?->amount) }}" placeholder="0.00" required inputmode="decimal">
                            @error('amount')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="benefit_effective_from">Effective from</label>
                            <input id="benefit_effective_from" class="input" type="date" name="effective_from" value="{{ old('effective_from', $activeBenefit?->effective_from?->toDateString() ?? now()->toDateString()) }}">
                        </div>
                        <button type="submit" class="btn-secondary btn-sm btn-block" :disabled="submitting">
                            <span x-show="!submitting">Add / update de minimis</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2"><span class="spinner"></span> Saving…</span>
                        </button>
                    </form>
                    <div class="border-t border-line px-4 pb-4 sm:px-5">
                        <p class="pt-3 text-[10px] font-bold uppercase tracking-widest text-muted">Configured · edit in place</p>
                        @if ($benefits->isEmpty())
                            <p class="py-3 text-sm text-muted">None configured yet.</p>
                        @else
                            <ul class="space-y-3 pt-2">
                                @foreach ($benefits as $benefit)
                                    <li>
                                        <form method="POST" action="{{ route('admin.payroll.employees.benefits.update', [$employee, $benefit]) }}" class="space-y-2 rounded-lg border border-line bg-surface-soft p-3 text-sm">
                                            @csrf
                                            @method('PUT')
                                            <p class="font-semibold text-ink">{{ $benefit->label ?? 'De minimis' }}</p>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <div>
                                                    <label class="label text-[10px]">Amount (₱)</label>
                                                    <input class="input input-sm" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $benefit->amount) }}" required inputmode="decimal">
                                                </div>
                                                <div>
                                                    <label class="label text-[10px]">Effective from</label>
                                                    <input class="input input-sm" type="date" name="effective_from" value="{{ old('effective_from', $benefit->effective_from?->toDateString()) }}">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn-outline btn-sm">Save</button>
                                        </form>
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
                        <p class="pt-3 text-[10px] font-bold uppercase tracking-widest text-muted">Declared · edit in place</p>
                        @if ($recurringDeductions->isEmpty())
                            <p class="py-3 text-sm text-muted">No recurring deductions on file.</p>
                        @else
                            <ul class="space-y-3 pt-2">
                                @foreach ($recurringDeductions as $deduction)
                                    <li>
                                        <form method="POST" action="{{ route('admin.payroll.employees.deductions.update', [$employee, $deduction]) }}" class="space-y-2 rounded-lg border border-line bg-surface-soft p-3 text-sm">
                                            @csrf
                                            @method('PUT')
                                            <p class="font-semibold text-ink">{{ $deduction->deductionType?->name ?? 'Deduction' }}</p>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <div>
                                                    <label class="label text-[10px]">Amount per cut-off (₱)</label>
                                                    <input class="input input-sm" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $deduction->amount) }}" required inputmode="decimal">
                                                </div>
                                                <div>
                                                    <label class="label text-[10px]">Effective from</label>
                                                    <input class="input input-sm" type="date" name="effective_from" value="{{ old('effective_from', $deduction->effective_from?->toDateString()) }}">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn-outline btn-sm">Save</button>
                                        </form>
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
                                <th class="text-right">Basic salary (₱)</th>
                                <th class="text-right">Gross compensation (₱)</th>
                                <th>Status</th>
                                <th class="w-24"></th>
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
                                    $formId = 'salary-history-'.$row->id;
                                    $rowBasic = old('basic_salary', $row->basic_salary ?? $row->semi_monthly_salary ?? $row->monthly_salary);
                                    $rowGross = old('gross_compensation', $row->gross_compensation ?? ((float) ($row->basic_salary ?? $row->semi_monthly_salary ?? 0) + $deMinimisPerCutoff));
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="align-top text-muted">
                                        {{ $row->effective_from->format('M j, Y') }}
                                        @if ($row->effective_to)
                                            <span class="block text-xs">– {{ $row->effective_to->format('M j, Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="align-top">
                                        <select class="select select-sm" name="salary_type" form="{{ $formId }}" required>
                                            @foreach (\App\Enums\SalaryType::cases() as $type)
                                                <option value="{{ $type->value }}" @selected(old('salary_type', $row->salary_type?->value) === $type->value)>{{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="align-top text-right">
                                        <input class="input input-sm w-full min-w-[6rem] text-right tabular-nums" type="number" step="0.01" min="0" name="basic_salary" form="{{ $formId }}" value="{{ $rowBasic }}" inputmode="decimal">
                                        <input type="hidden" name="working_hours_per_day" form="{{ $formId }}" value="{{ old('working_hours_per_day', $row->working_hours_per_day) }}">
                                        <input type="hidden" name="working_days_basis" form="{{ $formId }}" value="{{ old('working_days_basis', $row->working_days_basis) }}">
                                    </td>
                                    <td class="align-top text-right">
                                        <input class="input input-sm w-full min-w-[6rem] text-right tabular-nums" type="number" step="0.01" min="0" name="gross_compensation" form="{{ $formId }}" value="{{ $rowGross }}" inputmode="decimal">
                                        <p class="mt-1 text-[10px] text-muted">Basic + de minimis</p>
                                    </td>
                                    <td class="align-top">
                                        <span class="{{ $statusBadge }}">{{ $row->status?->label() ?? '—' }}</span>
                                    </td>
                                    <td class="align-top">
                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.payroll.employees.salary.update', [$employee, $row]) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn-outline btn-sm whitespace-nowrap">Save</button>
                                        </form>
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
