@php
    use App\Enums\ManualSalaryComputationMode;
    use App\Enums\ManualSalaryMonthlySubtype;
    use App\Enums\ManualSalaryPayableIntent;

    $mode = old('computation_mode', isset($computation) ? $computation->computation_mode->value : ManualSalaryComputationMode::Period->value);
@endphp

<div class="grid gap-6 lg:grid-cols-2" x-data="{
    mode: @js($mode),
    monthlySubtype: @js(old('monthly_subtype', isset($computation) && $computation->monthly_subtype ? $computation->monthly_subtype->value : ManualSalaryMonthlySubtype::FullMonth->value)),
    customLines: @js(old('custom_lines', [])),
    addLine(direction) {
        this.customLines.push({ direction, label: '', amount: '', category: 'custom' });
    },
    removeLine(index) { this.customLines.splice(index, 1); }
}">
    <div class="space-y-4">
        <div>
            <label class="label">Computation mode</label>
            <select name="computation_mode" class="select w-full" x-model="mode" required>
                @foreach (ManualSalaryComputationMode::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="label">Payable intent</label>
            <select name="payable_intent" class="select w-full" required>
                @foreach (ManualSalaryPayableIntent::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('payable_intent', isset($computation) ? $computation->payable_intent->value : ManualSalaryPayableIntent::Standard->value) === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-muted">Use supplemental or adjustment when another payroll already covers this period.</p>
        </div>

        <div x-show="mode === 'period'" x-cloak class="space-y-3">
            <div>
                <label class="label">Payroll period (optional)</label>
                <select name="payroll_period_id" class="select w-full" :disabled="mode !== 'period'">
                    <option value="">— Custom cutoff dates —</option>
                    @foreach ($payrollPeriods as $period)
                        <option value="{{ $period->id }}" @selected((string) old('payroll_period_id', isset($computation) ? $computation->payroll_period_id : '') === (string) $period->id)>
                            {{ $period->period_name }} ({{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}) · {{ $period->status?->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Cutoff start</label>
                    <input type="date" name="coverage_start" class="input w-full" :disabled="mode !== 'period'" value="{{ old('coverage_start', isset($computation) ? $computation->coverage_start?->format('Y-m-d') : '') }}">
                </div>
                <div>
                    <label class="label">Cutoff end</label>
                    <input type="date" name="coverage_end" class="input w-full" :disabled="mode !== 'period'" value="{{ old('coverage_end', isset($computation) ? $computation->coverage_end?->format('Y-m-d') : '') }}">
                </div>
            </div>
            <p class="text-xs text-muted">If you pick a payroll period above, cutoff dates are taken from that period—you can leave the date fields blank.</p>
        </div>

        <div x-show="mode === 'monthly'" x-cloak class="space-y-3">
            <div>
                <label class="label">Monthly coverage type</label>
                <select name="monthly_subtype" class="select w-full" x-model="monthlySubtype" :disabled="mode !== 'monthly'">
                    @foreach (ManualSalaryMonthlySubtype::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Payroll year</label>
                    <input type="number" name="payroll_year" class="input w-full" min="2000" max="2100" :disabled="mode !== 'monthly'" value="{{ old('payroll_year', isset($computation) ? $computation->payroll_year : now()->year) }}">
                </div>
                <div>
                    <label class="label">Payroll month</label>
                    <input type="number" name="payroll_month" class="input w-full" min="1" max="12" :disabled="mode !== 'monthly'" value="{{ old('payroll_month', isset($computation) ? $computation->payroll_month : now()->month) }}">
                </div>
            </div>
            <div x-show="monthlySubtype === 'partial_month'" class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Partial start</label>
                    <input type="date" name="coverage_start" class="input w-full" :disabled="mode !== 'monthly' || monthlySubtype !== 'partial_month'" value="{{ old('coverage_start', isset($computation) ? $computation->coverage_start?->format('Y-m-d') : '') }}">
                </div>
                <div>
                    <label class="label">Partial end</label>
                    <input type="date" name="coverage_end" class="input w-full" :disabled="mode !== 'monthly' || monthlySubtype !== 'partial_month'" value="{{ old('coverage_end', isset($computation) ? $computation->coverage_end?->format('Y-m-d') : '') }}">
                </div>
            </div>
        </div>

        <div>
            <label class="label">Remarks</label>
            <textarea name="remarks" class="input w-full min-h-[4rem]" rows="3">{{ old('remarks', isset($computation) ? $computation->remarks : '') }}</textarea>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card card-accent-info overflow-hidden">
            <div class="card-header"><h3 class="text-sm font-bold text-ink">Attendance &amp; time (optional overrides)</h3></div>
            <div class="form-card-body grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['scheduled_days', 'Regular days (scheduled)', 'number', '1'],
                    ['regular_hours', 'Regular hours', 'number', '0.01'],
                    ['paid_leave_days', 'Paid leave days', 'number', '0.01'],
                    ['unpaid_leave_days', 'Unpaid leave days', 'number', '0.01'],
                    ['absent_days', 'Absences', 'number', '0.01'],
                    ['late_minutes', 'Late minutes', 'number', '1'],
                    ['undertime_minutes', 'Undertime minutes', 'number', '1'],
                    ['approved_ot_hours', 'Approved OT hours', 'number', '0.01'],
                ] as [$key, $label, $type, $step])
                    <div>
                        <label class="label">{{ $label }}</label>
                        <input type="{{ $type }}" step="{{ $step }}" min="0" name="attendance[{{ $key }}]" class="input w-full tabular-nums"
                            value="{{ old("attendance.$key", isset($computation) ? ($computation->attendance_inputs[$key] ?? '') : '') }}">
                    </div>
                @endforeach
            </div>
            <p class="px-5 pb-4 text-xs text-muted">Leave blank to use DTR / payroll attendance for the selected coverage.</p>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between gap-2">
                <h3 class="text-sm font-bold text-ink">Custom earnings &amp; deductions</h3>
                <div class="flex gap-2">
                    <button type="button" class="btn-outline btn-sm" @click="addLine('earning')">+ Earning</button>
                    <button type="button" class="btn-outline btn-sm" @click="addLine('deduction')">+ Deduction</button>
                </div>
            </div>
            <template x-for="(line, index) in customLines" :key="index">
                <div class="mb-2 grid gap-2 rounded-lg border border-line p-3 sm:grid-cols-12">
                    <input type="hidden" :name="'custom_lines['+index+'][direction]'" x-model="line.direction">
                    <input class="input sm:col-span-5" :name="'custom_lines['+index+'][label]'" x-model="line.label" placeholder="Description" required>
                    <input class="input sm:col-span-3 tabular-nums" type="number" step="0.01" min="0" :name="'custom_lines['+index+'][amount]'" x-model="line.amount" placeholder="Amount" required>
                    <input class="input sm:col-span-3" :name="'custom_lines['+index+'][category]'" x-model="line.category" placeholder="Category">
                    <button type="button" class="btn-outline btn-sm sm:col-span-1" @click="removeLine(index)">×</button>
                </div>
            </template>
            <p class="text-xs text-muted">Custom lines require save/recalculate. Overrides to computed fields need a reason on the review screen.</p>
        </div>
    </div>
</div>
