@php
    $designation = $designation ?? null;
@endphp

<div class="space-y-6">
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Designation details</h2></div>
        <div class="grid gap-4 p-5 md:grid-cols-2">
            <div>
                <label class="label" for="designation_name">Designation name</label>
                <input id="designation_name" class="input @error('designation_name') input-error @enderror" name="designation_name"
                    value="{{ old('designation_name', $designation?->designation_name) }}" required>
                @error('designation_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="designation_code">Designation code</label>
                <input id="designation_code" class="input font-mono @error('designation_code') input-error @enderror" name="designation_code"
                    value="{{ old('designation_code', $designation?->designation_code) }}"
                    placeholder="{{ $designation ? '' : 'Auto-generated if blank' }}">
                @error('designation_code')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="department_id">Department</label>
                <select id="department_id" class="select" name="department_id">
                    <option value="">— None —</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(old('department_id', $designation?->department_id) == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="default_pay_type">Salary type (default)</label>
                <select id="default_pay_type" class="select" name="default_pay_type">
                    <option value="">— Not set —</option>
                    @foreach ($salaryTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('default_pay_type', $designation?->default_pay_type?->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="label" for="description">Description</label>
                <textarea id="description" class="textarea" name="description" rows="2">{{ old('description', $designation?->description) }}</textarea>
            </div>
            <div class="flex items-center gap-2 md:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1" class="checkbox"
                    @checked(old('is_active', $designation?->is_active ?? true))>
                <label for="is_active" class="text-sm font-medium text-ink">Active designation</label>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Default compensation (manual entry)</h2>
            <p class="mt-1 text-xs text-muted">Defaults for new employee salary assignments only — not employee actual pay.</p>
        </div>
        <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['default_basic_salary', 'Default basic salary (₱)', '0.01'],
                ['default_semi_monthly_salary', 'Default semi-monthly (₱)', '0.01'],
                ['default_daily_rate', 'Default daily rate (₱)', '0.01'],
                ['default_hourly_rate', 'Default hourly rate (₱)', '0.0001'],
            ] as [$field, $label, $step])
                <div>
                    <label class="label" for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" class="input tabular-nums" type="number" step="{{ $step }}" min="0" name="{{ $field }}"
                        value="{{ old($field, $designation?->$field) }}">
                    @error($field)<p class="error-text">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label class="label" for="default_working_hours_per_day">Working hours / day</label>
                <input id="default_working_hours_per_day" class="input" type="number" min="1" max="24" name="default_working_hours_per_day"
                    value="{{ old('default_working_hours_per_day', $designation?->default_working_hours_per_day ?? 8) }}">
            </div>
            <div>
                <label class="label" for="default_working_days_per_period">Working days / payroll period</label>
                <input id="default_working_days_per_period" class="input" type="number" min="1" max="31" name="default_working_days_per_period"
                    value="{{ old('default_working_days_per_period', $designation?->default_working_days_per_period) }}"
                    placeholder="e.g. 11">
            </div>
        </div>
        <p class="border-t border-line px-5 pb-5 text-xs text-muted">
            Overtime and holiday/premium multipliers are configured under Payroll → Payroll configuration.
        </p>
    </div>
</div>
