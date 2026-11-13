@php
    $obRequest = $obRequest ?? null;
    $defaultStation = 'Palawan';
    $externalRows = old('external_participants', $externalParticipants ?? ['']);
    if ($externalRows === []) {
        $externalRows = [''];
    }
    $departureValue = old('departure_time');
    if ($departureValue === null && filled($obRequest?->departure_time)) {
        $raw = (string) $obRequest->departure_time;
        $departureValue = strlen($raw) >= 5 ? substr($raw, 0, 5) : $raw;
    }
@endphp

<div class="card card-accent-brand">
    <div class="card-header"><h2 class="card-title">Requester information</h2></div>
    <div class="card-body grid gap-4 sm:grid-cols-2">
        <div><div class="label">Requester name</div><div class="input bg-canvas">{{ $employee->fullName() }}</div></div>
        <div><div class="label">Employee ID</div><div class="input bg-canvas">{{ $employee->employee_number }}</div></div>
        <div><div class="label">Department</div><div class="input bg-canvas">{{ $employee->department?->name ?? '—' }}</div></div>
        <div><div class="label">Position</div><div class="input bg-canvas">{{ $employee->position ?? '—' }}</div></div>
        <div><div class="label">Date filed</div><div class="input bg-canvas">{{ now()->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</div></div>
    </div>
</div>

<div class="card card-accent-gold">
    <div class="card-header"><h2 class="card-title">Participants (max 10)</h2></div>
    <div class="card-body space-y-4"
         x-data="travelEmployeePicker({ selected: @js($selectedParticipants ?? []), searchUrl: @js($searchUrl), inputName: 'participant_employee_ids' })">
        <div>
            <label class="label">Search employee</label>
            <input type="text" class="input" placeholder="Type employee name, ID, department, or position…" x-model="query" @input.debounce.300ms="search">
            <div x-show="open && results.length" x-cloak class="mt-2 overflow-hidden rounded-xl border border-line bg-surface shadow-lg">
                <template x-for="person in results" :key="person.id">
                    <button type="button" class="block w-full px-4 py-2.5 text-left text-sm hover:bg-brand-50" @click="add(person)">
                        <span class="font-semibold text-ink" x-text="person.name"></span>
                        <span class="text-xs text-muted" x-text="(person.position || '') + (person.department ? ' · ' + person.department : '')"></span>
                    </button>
                </template>
            </div>
        </div>
        <div>
            <div class="label">Selected employees</div>
            <p class="hint mb-2" x-show="!selected.length">No employees selected yet. Search above to add participants.</p>
            <div class="overflow-hidden rounded-xl border border-line bg-surface" x-show="selected.length" x-cloak>
                <ul class="divide-y divide-line">
                    <template x-for="(person, index) in selected" :key="person.id">
                        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-xs font-bold tabular-nums text-brand-800" x-text="index + 1"></span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-ink" x-text="person.name"></p>
                                    <p class="truncate text-xs text-muted" x-text="(person.position || '—') + (person.department ? ' · ' + person.department : '')"></p>
                                </div>
                            </div>
                            <button type="button" class="btn-outline btn-sm shrink-0 border-critical-200 text-critical-700 hover:bg-critical-50" @click="remove(person.id)">Remove</button>
                            <input type="hidden" :name="inputFieldName()" :value="person.id">
                        </li>
                    </template>
                </ul>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_requester_as_participant" value="1" class="checkbox" @checked(old('include_requester_as_participant', $obRequest && $obRequest->participants->contains('employee_id', $employee->id)))>
            <span>Include myself as a participant</span>
        </label>

        <div x-data="{ rows: @js($externalRows) }">
            <div class="label">External participants (not in employee roster)</div>
            <p class="hint">Names only — combined employee + external list cannot exceed 10.</p>
            <template x-for="(row, index) in rows" :key="index">
                <div class="mt-2 flex gap-2">
                    <input type="text" class="input" name="external_participants[]" x-model="rows[index]" placeholder="Full name">
                    <button type="button" class="btn btn-secondary" x-show="rows.length > 1" @click="rows.splice(index, 1)">Remove</button>
                </div>
            </template>
            <button type="button" class="btn btn-secondary mt-2" @click="rows.push('')">Add external name</button>
        </div>

        @error('participant_employee_ids') <p class="error-text">{{ $message }}</p> @enderror
        @error('external_participants') <p class="error-text">{{ $message }}</p> @enderror
        @error('participants') <p class="error-text">{{ $message }}</p> @enderror
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Official business details</h2></div>
    <div class="card-body space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="official_station">Official station</label>
                <input id="official_station" name="official_station" class="input" value="{{ old('official_station', $obRequest?->official_station ?? $defaultStation) }}">
            </div>
            <div>
                <label class="label" for="departure_time">Departure time</label>
                <input id="departure_time" type="time" name="departure_time" class="input" value="{{ $departureValue }}">
                @error('departure_time') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="destination_from">From</label>
                <input id="destination_from" name="destination_from" class="input" value="{{ old('destination_from', $obRequest?->destination_from) }}" placeholder="Place of origin">
            </div>
            <div>
                <label class="label" for="destination_to">To / destination</label>
                <input id="destination_to" name="destination_to" class="input" value="{{ old('destination_to', $obRequest?->destination_to) }}" placeholder="Official business destination">
                @error('destination_to') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="date_covered_start">Date covered (from)</label>
                <input id="date_covered_start" type="date" name="date_covered_start" class="input" value="{{ old('date_covered_start', optional($obRequest?->date_covered_start)->format('Y-m-d')) }}">
                @error('date_covered_start') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="date_covered_end">Date covered (to)</label>
                <input id="date_covered_end" type="date" name="date_covered_end" class="input" value="{{ old('date_covered_end', optional($obRequest?->date_covered_end)->format('Y-m-d')) }}">
                @error('date_covered_end') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="label" for="purpose">Purpose of official business</label>
            <textarea id="purpose" name="purpose" rows="3" class="textarea">{{ old('purpose', $obRequest?->purpose) }}</textarea>
            @error('purpose') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="purpose_particular">Particulars (if any)</label>
            <textarea id="purpose_particular" name="purpose_particular" rows="2" class="textarea">{{ old('purpose_particular', $obRequest?->purpose_particular) }}</textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="transportation">Transportation / via</label>
                <select id="transportation" name="transportation" class="select">
                    <option value="">Select</option>
                    @foreach ($transportOptions as $option)
                        <option value="{{ $option->value }}" @selected(old('transportation', $obRequest?->transportation_mode?->value) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="transportation_other">Other (specify)</label>
                <input id="transportation_other" name="transportation_other" class="input" value="{{ old('transportation_other', $obRequest?->transportation_other) }}">
            </div>
            <div>
                <label class="label" for="vehicle_name">Vehicle</label>
                <input id="vehicle_name" name="vehicle_name" class="input" value="{{ old('vehicle_name', $obRequest?->vehicle_name) }}">
            </div>
            <div>
                <label class="label" for="plate_number">Plate number</label>
                <input id="plate_number" name="plate_number" class="input" value="{{ old('plate_number', $obRequest?->plate_number) }}">
            </div>
            <div>
                <label class="label" for="accommodation_type">Accommodation</label>
                <input id="accommodation_type" name="accommodation_type" class="input" value="{{ old('accommodation_type', $obRequest?->accommodation_type) }}" placeholder="e.g. Hotel, None">
            </div>
            <div>
                <label class="label" for="accommodation_particular">Accommodation particulars</label>
                <input id="accommodation_particular" name="accommodation_particular" class="input" value="{{ old('accommodation_particular', $obRequest?->accommodation_particular) }}">
            </div>
        </div>
    </div>
</div>
