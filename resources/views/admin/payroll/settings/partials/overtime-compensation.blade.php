<div class="card overflow-hidden lg:col-span-2">
    <div class="card-header flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="card-title">Overtime compensation configuration</h2>
            <p class="mt-1 text-xs text-muted">Configurable OT multipliers by day type, rest day, and night shift. Non-OT night reference rows are stored for policy reference only.</p>
        </div>
        <form method="POST" action="{{ route('admin.payroll.settings.overtime-rates.load-defaults') }}" class="inline-flex items-end gap-2">
            @csrf
            <div>
                <label class="label text-xs" for="ot_defaults_effective_from">Effective from</label>
                <input id="ot_defaults_effective_from" class="input input-sm" type="date" name="effective_from"
                    value="{{ old('effective_from', now()->toDateString()) }}" required>
            </div>
            <button type="submit" class="btn-outline btn-sm">Load default OT rates</button>
        </form>
    </div>

    <div class="border-b border-line p-5">
        <form method="POST" action="{{ route('admin.payroll.settings.overtime-rates.store') }}" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div class="lg:col-span-3">
                <label class="label" for="ot_label">Label</label>
                <input id="ot_label" class="input" name="label" value="{{ old('label') }}" required>
            </div>
            <div>
                <label class="label" for="ot_workday_classification">Workday classification</label>
                <select id="ot_workday_classification" class="select" name="workday_classification" required>
                    @foreach ($workdayClassifications as $classification)
                        <option value="{{ $classification->value }}" @selected(old('workday_classification') === $classification->value)>
                            {{ $classification->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="ot_multiplier_input">Multiplier or percentage</label>
                <input id="ot_multiplier_input" class="input" name="multiplier_input" placeholder="1.25 or 125%"
                    value="{{ old('multiplier_input') }}" required>
                <p class="mt-1 text-xs text-muted">Stored as multiplier; equivalent % shown in the table.</p>
            </div>
            <div>
                <label class="label" for="ot_effective_from">Effective from</label>
                <input id="ot_effective_from" class="input" type="date" name="effective_from"
                    value="{{ old('effective_from', now()->toDateString()) }}" required>
            </div>
            <div class="flex flex-col gap-2">
                <span class="label">Conditions</span>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_rest_day" value="0">
                    <input type="checkbox" class="checkbox" name="is_rest_day" value="1" @checked(old('is_rest_day'))> Rest day
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_night_shift" value="0">
                    <input type="checkbox" class="checkbox" name="is_night_shift" value="1" @checked(old('is_night_shift'))> Night shift differential
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_overtime" value="0">
                    <input type="checkbox" class="checkbox" name="is_overtime" value="1" @checked(old('is_overtime', '1'))> Overtime eligible
                </label>
            </div>
            <div>
                <label class="label" for="ot_effective_to">Effective to (optional)</label>
                <input id="ot_effective_to" class="input" type="date" name="effective_to" value="{{ old('effective_to') }}">
            </div>
            <div>
                <label class="label" for="ot_remarks">Remarks</label>
                <input id="ot_remarks" class="input" name="remarks" value="{{ old('remarks') }}">
            </div>
            <div>
                <label class="label" for="ot_change_reason">Reason for change</label>
                <input id="ot_change_reason" class="input" name="change_reason" value="{{ old('change_reason') }}">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary">Add OT rate</button>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table text-sm">
            <thead>
                <tr>
                    <th>Condition</th>
                    <th>Flags</th>
                    <th class="text-right">Multiplier</th>
                    <th class="text-right">%</th>
                    <th>Effective</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($otCompensationRates as $rate)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink">{{ $rate->label }}</div>
                            @if ($rate->condition_code)
                                <div class="text-xs text-muted">{{ $rate->condition_code }}</div>
                            @endif
                            @if ($rate->remarks)
                                <div class="text-xs text-muted">{{ $rate->remarks }}</div>
                            @endif
                        </td>
                        <td class="text-xs text-muted">
                            {{ $rate->workday_classification->label() }}<br>
                            @if ($rate->is_rest_day) Rest day · @endif
                            @if ($rate->is_night_shift) Night · @endif
                            {{ $rate->is_overtime ? 'OT' : 'Non-OT ref' }}
                        </td>
                        <td class="text-right tabular-nums">{{ number_format((float) $rate->multiplier, 4) }}×</td>
                        <td class="text-right tabular-nums">{{ number_format($rate->percentage(), 2) }}%</td>
                        <td class="text-xs">
                            {{ $rate->effective_from->format('Y-m-d') }}
                            @if ($rate->effective_to)
                                – {{ $rate->effective_to->format('Y-m-d') }}
                            @endif
                        </td>
                        <td>
                            <span class="{{ $rate->is_active ? 'badge-brand' : 'badge-neutral' }}">
                                {{ $rate->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.payroll.settings.overtime-rates.update', $rate) }}" class="inline-flex flex-wrap items-center justify-end gap-2">
                                @csrf @method('PUT')
                                <input type="text" class="input w-24 text-sm" name="multiplier_input" value="{{ $rate->multiplier }}" required>
                                <select name="is_active" class="select w-auto text-sm">
                                    <option value="1" @selected($rate->is_active)>Active</option>
                                    <option value="0" @selected(! $rate->is_active)>Inactive</option>
                                </select>
                                <input type="date" class="input w-36 text-sm" name="effective_from" value="{{ $rate->effective_from->format('Y-m-d') }}">
                                <button type="submit" class="btn-outline btn-sm">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-muted">
                            No OT compensation rates yet. Use <strong>Load default OT rates</strong> or add a rate manually.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
