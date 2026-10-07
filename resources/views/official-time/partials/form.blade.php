@php
    $otModel = $ot ?? null;
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2 rounded-xl border border-line bg-surface-50 p-4 text-sm">
        <div class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Employee Information</div>
        <div class="grid gap-2 sm:grid-cols-2">
            <div><span class="text-muted">Name:</span> <span class="font-semibold">{{ $employee->fullName() }}</span></div>
            <div><span class="text-muted">Employee No.:</span> <span class="font-mono">{{ $employee->employee_number }}</span></div>
            <div><span class="text-muted">Department:</span> {{ $employee->department?->name ?? '—' }}</div>
            <div><span class="text-muted">Designation:</span> {{ $employee->designation?->name ?? '—' }}</div>
        </div>
    </div>

    <div>
        <label class="label" for="official_time_type_id">Official Time Type</label>
        <select id="official_time_type_id" name="official_time_type_id" class="select" required>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected(old('official_time_type_id', $otModel?->official_time_type_id) == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="date">Official Time Date</label>
        <input id="date" type="date" name="date" class="input" required value="{{ old('date', $otModel?->date?->toDateString() ?? now()->toDateString()) }}">
    </div>
    <div class="sm:col-span-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="am_time_in">AM Time In</label>
            <input id="am_time_in" type="time" name="am_time_in" class="input" required value="{{ old('am_time_in', $otModel?->punchTimeValue('am_time_in') ?: '08:00') }}">
        </div>
        <div>
            <label class="label" for="am_time_out">AM Time Out</label>
            <input id="am_time_out" type="time" name="am_time_out" class="input" required value="{{ old('am_time_out', $otModel?->punchTimeValue('am_time_out') ?: '12:00') }}">
        </div>
        <div>
            <label class="label" for="pm_time_in">PM Time In</label>
            <input id="pm_time_in" type="time" name="pm_time_in" class="input" required value="{{ old('pm_time_in', $otModel?->punchTimeValue('pm_time_in') ?: '13:00') }}">
        </div>
        <div>
            <label class="label" for="pm_time_out">PM Time Out</label>
            <input id="pm_time_out" type="time" name="pm_time_out" class="input" required value="{{ old('pm_time_out', $otModel?->punchTimeValue('pm_time_out') ?: '17:00') }}">
        </div>
    </div>
    <div class="sm:col-span-2">
        <label class="label" for="attachment">Supporting Document (optional unless required by type)</label>
        <input id="attachment" type="file" name="attachment" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp">
        @if ($otModel?->attachment_name)
            <p class="mt-1 text-xs text-muted">Current file: {{ $otModel->attachment_name }}</p>
        @endif
    </div>
</div>
