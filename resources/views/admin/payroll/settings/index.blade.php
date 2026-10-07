@extends('layouts.app')

@section('title', 'Payroll configuration')
@section('page-title', 'Payroll configuration')
@section('page-subtitle', 'Overtime, statutory defaults, and holiday / rest-day premium rules')

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('admin.payroll.dashboard') }}" class="btn-outline btn-sm">← Payroll dashboard</a>
    <a href="{{ route('admin.designations.index') }}" class="btn-outline btn-sm">Designation master</a>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header"><h2 class="card-title">General settings</h2></div>
        <form method="POST" action="{{ route('admin.payroll.settings.general') }}" class="space-y-4 p-5">
            @csrf
            <div>
                <label class="label" for="payroll_overtime_multiplier">Overtime multiplier</label>
                <input id="payroll_overtime_multiplier" class="input" type="number" step="0.01" min="1" max="5"
                    name="payroll_overtime_multiplier" value="{{ old('payroll_overtime_multiplier', $otMultiplier) }}" required>
                <p class="mt-1 text-xs text-muted">Applied to approved OT hours × hourly rate (default 1.25).</p>
            </div>
            <div>
                <label class="label" for="payroll_holiday_pay_mode">Holiday pay mode</label>
                <select id="payroll_holiday_pay_mode" class="select" name="payroll_holiday_pay_mode" required>
                    <option value="premium_only" @selected($holidayPayMode === 'premium_only')>Premium only (multiplier − 1 × hours)</option>
                    <option value="full_multiplier" @selected($holidayPayMode === 'full_multiplier')>Full multiplier (hours × rate × multiplier)</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="payroll_ot_requires_approval" value="0">
                <input id="payroll_ot_requires_approval" type="checkbox" class="checkbox" name="payroll_ot_requires_approval" value="1"
                    @checked(old('payroll_ot_requires_approval', $otRequiresApproval))>
                <label for="payroll_ot_requires_approval" class="text-sm">Require OT approval before payroll uses OT hours</label>
            </div>
            <div>
                <label class="label" for="payroll_philhealth_rate">PhilHealth rate (% of gross comp.)</label>
                <input id="payroll_philhealth_rate" class="input" type="number" step="0.01" min="0" max="100"
                    name="payroll_philhealth_rate" value="{{ old('payroll_philhealth_rate', $philhealthRate) }}">
                <p class="mt-1 text-xs text-muted">Auto-deducted when employee has no active PhilHealth deduction line.</p>
            </div>
            <div>
                <label class="label" for="payroll_hdmf_amount">HDMF employee share (fixed per period)</label>
                <input id="payroll_hdmf_amount" class="input" type="number" step="0.01" min="0"
                    name="payroll_hdmf_amount" value="{{ old('payroll_hdmf_amount', $hdmfAmount) }}">
            </div>
            <div>
                <label class="label" for="payroll_working_days_basis">Working days basis (salary derivation)</label>
                <input id="payroll_working_days_basis" class="input" type="number" min="1" max="31"
                    name="payroll_working_days_basis" value="{{ old('payroll_working_days_basis', $workingDaysBasis) }}" required>
            </div>
            <div class="space-y-2 border-t border-line pt-4">
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payroll_sss_from_brackets" value="0">
                    <input id="payroll_sss_from_brackets" type="checkbox" class="checkbox" name="payroll_sss_from_brackets" value="1"
                        @checked(old('payroll_sss_from_brackets', $sssFromBrackets))>
                    <label for="payroll_sss_from_brackets" class="text-sm">Auto SSS from bracket table (semi-monthly share)</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payroll_block_finalize_on_warnings" value="0">
                    <input id="payroll_block_finalize_on_warnings" type="checkbox" class="checkbox" name="payroll_block_finalize_on_warnings" value="1"
                        @checked(old('payroll_block_finalize_on_warnings', $blockFinalizeOnWarnings))>
                    <label for="payroll_block_finalize_on_warnings" class="text-sm">Block finalize until warnings are acknowledged</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payroll_email_on_paid" value="0">
                    <input id="payroll_email_on_paid" type="checkbox" class="checkbox" name="payroll_email_on_paid" value="1"
                        @checked(old('payroll_email_on_paid', $emailPayslipsOnPaid))>
                    <label for="payroll_email_on_paid" class="text-sm">Email payslip links when period is marked paid</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payroll_ot_central_approval" value="0">
                    <input id="payroll_ot_central_approval" type="checkbox" class="checkbox" name="payroll_ot_central_approval" value="1"
                        @checked(old('payroll_ot_central_approval', $otCentralApproval))>
                    <label for="payroll_ot_central_approval" class="text-sm">Route OT through central approval workflow (Settings → Approval Workflow → Overtime)</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payroll_tax_from_brackets" value="0">
                    <input id="payroll_tax_from_brackets" type="checkbox" class="checkbox" name="payroll_tax_from_brackets" value="1"
                        @checked(old('payroll_tax_from_brackets', $taxFromBrackets))>
                    <label for="payroll_tax_from_brackets" class="text-sm">Auto withholding tax from bracket table</label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="payroll_unworked_regular_holiday_pay" value="0">
                    <input id="payroll_unworked_regular_holiday_pay" type="checkbox" class="checkbox" name="payroll_unworked_regular_holiday_pay" value="1"
                        @checked(old('payroll_unworked_regular_holiday_pay', $unworkedHolidayPay))>
                    <label for="payroll_unworked_regular_holiday_pay" class="text-sm">Pay unworked regular holidays (enable rule in premium table)</label>
                </div>
            </div>
            <button type="submit" class="btn-primary btn-block">Save general settings</button>
        </form>
    </div>

    <div class="card overflow-hidden lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title">Holiday &amp; rest-day premium rules</h2>
            <span class="chip">{{ $rules->count() }} rules</span>
        </div>
        <div class="table-wrap">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Scenario</th>
                        <th>Component</th>
                        <th class="text-right">Multiplier</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr>
                            <td>
                                <div class="font-semibold text-ink">{{ $rule->name }}</div>
                                @if ($rule->description)
                                    <div class="text-xs text-muted">{{ $rule->description }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ str_replace('_', ' ', $rule->pay_component) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($rule->multiplier, 2) }}×</td>
                            <td>
                                <span class="{{ $rule->is_active ? 'badge-brand' : 'badge-neutral' }}">
                                    {{ $rule->is_active ? 'Active' : 'Off' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.payroll.settings.rules.update', $rule) }}" class="inline-flex flex-wrap items-center justify-end gap-2">
                                    @csrf @method('PUT')
                                    <input type="number" step="0.01" min="1" max="10" class="input w-24 text-sm" name="multiplier" value="{{ $rule->multiplier }}" required>
                                    <select name="is_active" class="select w-auto text-sm">
                                        <option value="1" @selected($rule->is_active)>Active</option>
                                        <option value="0" @selected(! $rule->is_active)>Off</option>
                                    </select>
                                    <button type="submit" class="btn-outline btn-sm">Update</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
