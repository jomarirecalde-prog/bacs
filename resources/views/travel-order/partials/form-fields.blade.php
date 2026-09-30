@php
    $order = $order ?? null;
    $destinations = old('destinations', $order?->destinations->pluck('destination')->all() ?? ['']);
    if ($destinations === []) {
        $destinations = [''];
    }
@endphp

<div class="card card-accent-brand">
    <div class="card-header"><h2 class="card-title">Requester information</h2></div>
    <div class="card-body grid gap-4 sm:grid-cols-2">
        <div><div class="label">Requester name</div><div class="input bg-canvas">{{ $employee->fullName() }}</div></div>
        <div><div class="label">Employee ID</div><div class="input bg-canvas">{{ $employee->employee_number }}</div></div>
        <div><div class="label">Department</div><div class="input bg-canvas">{{ $employee->department?->name ?? '—' }}</div></div>
        <div><div class="label">Position</div><div class="input bg-canvas">{{ $employee->position ?? '—' }}</div></div>
        <div><div class="label">Date requested</div><div class="input bg-canvas">{{ now()->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</div></div>
    </div>
</div>

<div class="card card-accent-gold">
    <div class="card-header"><h2 class="card-title">Involved personnel / travelers</h2></div>
    <div class="card-body space-y-4" x-data="travelEmployeePicker({ selected: @js($selectedTravelers ?? []), searchUrl: @js($searchUrl) })">
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
        <div class="flex flex-wrap gap-2">
            <template x-for="person in selected" :key="person.id">
                <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1.5 text-sm">
                    <span x-text="person.name + (person.position ? ' – ' + person.position : '')"></span>
                    <button type="button" class="text-critical-600" @click="remove(person.id)" aria-label="Remove">&times;</button>
                    <input type="hidden" name="traveler_ids[]" :value="person.id">
                </span>
            </template>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_requester_as_traveler" value="1" class="checkbox" @checked(old('include_requester_as_traveler', $order && $order->personnel->contains('employee_id', $employee->id)))>
            <span>Include myself as one of the travelers</span>
        </label>
        @error('traveler_ids') <p class="error-text">{{ $message }}</p> @enderror
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Travel information</h2></div>
    <div class="card-body space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="official_station">Official station</label>
                <input id="official_station" name="official_station" class="input" value="{{ old('official_station', $order?->official_station) }}">
            </div>
            <div>
                <label class="label" for="number_of_bh">Number of BH</label>
                <input id="number_of_bh" name="number_of_bh" class="input" value="{{ old('number_of_bh', $order?->number_of_bh) }}">
            </div>
        </div>

        <div x-data="{ rows: @js($destinations) }">
            <div class="label">Destination/s</div>
            <template x-for="(row, index) in rows" :key="index">
                <div class="mt-2 flex gap-2">
                    <input type="text" class="input" name="destinations[]" x-model="rows[index]" placeholder="Destination">
                    <button type="button" class="btn btn-secondary" x-show="rows.length > 1" @click="rows.splice(index, 1)">Remove</button>
                </div>
            </template>
            <button type="button" class="btn btn-secondary mt-2" @click="rows.push('')">Add destination</button>
            @error('destinations') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="date_start">Date covered (from)</label>
                <input id="date_start" type="date" name="date_start" class="input" value="{{ old('date_start', optional($order?->date_start)->format('Y-m-d')) }}" required>
                @error('date_start') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="date_end">Date covered (to)</label>
                <input id="date_end" type="date" name="date_end" class="input" value="{{ old('date_end', optional($order?->date_end)->format('Y-m-d')) }}" required>
                @error('date_end') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="label" for="purpose">Purpose of travel</label>
            <textarea id="purpose" name="purpose" rows="3" class="textarea" required>{{ old('purpose', $order?->purpose) }}</textarea>
            @error('purpose') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="equipment">Equipment</label><input id="equipment" name="equipment" class="input" value="{{ old('equipment', $order?->equipment) }}"></div>
            <div><label class="label" for="project_name">Project name</label><input id="project_name" name="project_name" class="input" value="{{ old('project_name', $order?->project_name) }}"></div>
            <div><label class="label" for="client_company">Client / company</label><input id="client_company" name="client_company" class="input" value="{{ old('client_company', $order?->client_company) }}"></div>
            <div>
                <label class="label" for="transportation">Transportation / via</label>
                <select id="transportation" name="transportation" class="select">
                    <option value="">Select</option>
                    @foreach ($transportOptions as $option)
                        <option value="{{ $option->value }}" @selected(old('transportation', $order?->transportation?->value) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label" for="transportation_other">Other (specify)</label><input id="transportation_other" name="transportation_other" class="input" value="{{ old('transportation_other', $order?->transportation_other) }}"></div>
            <div><label class="label" for="vehicle_type">Vehicle type</label><input id="vehicle_type" name="vehicle_type" class="input" value="{{ old('vehicle_type', $order?->vehicle_type) }}"></div>
            <div><label class="label" for="plate_number">Plate number</label><input id="plate_number" name="plate_number" class="input" value="{{ old('plate_number', $order?->plate_number) }}"></div>
        </div>

        <div>
            <label class="label" for="remarks">Additional remarks</label>
            <textarea id="remarks" name="remarks" rows="2" class="textarea">{{ old('remarks', $order?->remarks) }}</textarea>
        </div>

        <div>
            <label class="label" for="attachments">Supporting documents</label>
            <input id="attachments" type="file" name="attachments[]" class="input" multiple accept=".pdf,image/*">
            <p class="hint">PDF or images, up to 5 MB each.</p>
        </div>
    </div>
</div>
