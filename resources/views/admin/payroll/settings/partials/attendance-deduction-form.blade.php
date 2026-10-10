@php
    $prefix = $prefix ?? 'late';
    $rule = $rule ?? [];
    $title = $title ?? 'Attendance deduction';
    $action = $action ?? '';
@endphp
<div class="card overflow-hidden">
    <div class="card-header"><h2 class="card-title">{{ $title }}</h2></div>
    <form method="POST" action="{{ $action }}" class="space-y-4 p-5">
        @csrf
        <div>
            <label class="label" for="{{ $prefix }}_effective_from">Effective from</label>
            <input id="{{ $prefix }}_effective_from" class="input" type="date" name="effective_from"
                value="{{ old('effective_from', now()->toDateString()) }}" required>
            <p class="mt-1 text-xs text-muted">Rules apply to payroll periods whose end date is on or after this date. Finalized or paid periods are not recalculated automatically.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="{{ $prefix }}_enabled" value="0">
            <input id="{{ $prefix }}_enabled" type="checkbox" class="checkbox" name="{{ $prefix }}_enabled" value="1"
                @checked(old("{$prefix}_enabled", $rule['enabled'] ?? true))>
            <label for="{{ $prefix }}_enabled" class="text-sm">Enable {{ strtolower($title) }}</label>
        </div>
        <div>
            <label class="label" for="{{ $prefix }}_calculation_mode">Calculation mode</label>
            <select id="{{ $prefix }}_calculation_mode" class="select" name="{{ $prefix }}_calculation_mode" required>
                <option value="derived_minute_rate" @selected(old("{$prefix}_calculation_mode", $rule['calculation_mode'] ?? '') === 'derived_minute_rate')>Salary-derived rate (hourly or daily ÷ minutes)</option>
                <option value="fixed_per_minute" @selected(old("{$prefix}_calculation_mode", $rule['calculation_mode'] ?? '') === 'fixed_per_minute')>Fixed amount per minute (e.g. ₱5/min)</option>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="{{ $prefix }}_fixed_per_minute">Amount per minute (₱)</label>
                <input id="{{ $prefix }}_fixed_per_minute" class="input" type="number" step="0.01" min="0"
                    name="{{ $prefix }}_fixed_per_minute" value="{{ old("{$prefix}_fixed_per_minute", $rule['fixed_per_minute'] ?? 0) }}">
            </div>
            <div>
                <label class="label" for="{{ $prefix }}_minute_rate_from">Derived minute rate from</label>
                <select id="{{ $prefix }}_minute_rate_from" class="select" name="{{ $prefix }}_minute_rate_from">
                    <option value="hourly" @selected(old("{$prefix}_minute_rate_from", $rule['minute_rate_from'] ?? 'hourly') === 'hourly')>Hourly rate ÷ 60</option>
                    <option value="daily" @selected(old("{$prefix}_minute_rate_from", $rule['minute_rate_from'] ?? '') === 'daily')>Daily rate ÷ work hours ÷ 60</option>
                </select>
            </div>
            <div>
                <label class="label" for="{{ $prefix }}_minimum_billable_minutes">Minimum minutes before penalty</label>
                <input id="{{ $prefix }}_minimum_billable_minutes" class="input" type="number" min="0" max="480"
                    name="{{ $prefix }}_minimum_billable_minutes" value="{{ old("{$prefix}_minimum_billable_minutes", $rule['minimum_billable_minutes'] ?? 0) }}">
                <p class="mt-1 text-xs text-muted">Schedule grace periods are applied when DTR is computed; this threshold is an additional payroll penalty rule.</p>
            </div>
            <div>
                <label class="label" for="{{ $prefix }}_round_minutes">Minute rounding</label>
                <select id="{{ $prefix }}_round_minutes" class="select" name="{{ $prefix }}_round_minutes">
                    @foreach (['none' => 'None', 'ceil' => 'Round up', 'floor' => 'Round down', 'nearest' => 'Nearest minute'] as $val => $label)
                        <option value="{{ $val }}" @selected(old("{$prefix}_round_minutes", $rule['round_minutes'] ?? 'none') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="text-xs text-muted">
            Minutes come from payroll attendance summaries (actual DTR vs work schedule). Example: fixed ₱5/min with 10 late minutes → ₱50 when enabled and above the minimum threshold.
        </p>
        <button type="submit" class="btn-primary btn-block">Save {{ strtolower($title) }}</button>
    </form>
</div>
