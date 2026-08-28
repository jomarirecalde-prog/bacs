<div class="card">
    <div class="card-header">
        <h2 class="card-title">Target audience</h2>
        <span class="text-xs text-muted">Who can see this event</span>
    </div>
    <div class="card-body space-y-4">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach ($audiences as $option)
                <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2.5 transition"
                       :class="audience === @js($option->value)
                           ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300'
                           : 'border-line bg-surface hover:border-brand-200 hover:bg-brand-50/40'">
                    <input type="radio" name="audience_type" value="{{ $option->value }}" x-model="audience" class="radio" required>
                    <span class="text-sm font-semibold text-ink">{{ $option->label() }}</span>
                </label>
            @endforeach
        </div>
        @error('audience_type') <p class="error-text">{{ $message }}</p> @enderror

        {{-- ------------------------------------------------- Departments --}}
        <div x-show="audience === 'departments'" x-cloak class="rounded-xl border border-info-200 bg-info-50/40 p-3">
            <div class="mb-2 flex items-center justify-between">
                <span class="label mb-0">Select departments</span>
                <span class="text-xs text-muted">{{ $departments->count() }} available</span>
            </div>
            @if ($departments->isEmpty())
                <p class="text-sm text-muted">No departments exist yet.</p>
            @else
                <div class="grid max-h-56 grid-cols-1 gap-1.5 overflow-y-auto sm:grid-cols-2">
                    @foreach ($departments as $department)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-surface px-2.5 py-2 transition hover:bg-info-50">
                            <input type="checkbox" name="department_ids[]" value="{{ $department->id }}" class="checkbox"
                                   :disabled="audience !== 'departments'"
                                   @checked(in_array($department->id, (array) $selectedDepartments))>
                            <span class="truncate text-sm text-ink">{{ $department->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            @error('department_ids') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        {{-- --------------------------------------------------- Employees --}}
        <div x-show="audience === 'employees'" x-cloak class="rounded-xl border border-gold-200 bg-gold-50/40 p-3">
            <div class="mb-2 flex items-center justify-between gap-2">
                <span class="label mb-0">Select employees</span>
                <span class="badge-gold">
                    <span x-text="selectedEmployees.length"></span> selected
                </span>
            </div>

            <div class="relative mb-2">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="employeeSearch" class="input pl-9"
                       placeholder="Search by name, employee number, or department">
            </div>

            @if ($employees->isEmpty())
                <p class="text-sm text-muted">No employees exist yet.</p>
            @else
                <div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-line bg-surface p-1.5">
                    @foreach ($employees as $employee)
                        @php
                            $needle = strtolower(trim(
                                $employee->fullName().' '.
                                $employee->employee_number.' '.
                                ($employee->department?->name ?? '')
                            ));
                        @endphp
                        <label x-show="!employeeSearch || @js($needle).includes(employeeSearch.toLowerCase())"
                               class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 transition hover:bg-gold-50">
                            <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}"
                                   x-model="selectedEmployees" class="checkbox"
                                   :disabled="audience !== 'employees'">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink">{{ $employee->fullName() }}</span>
                                <span class="block truncate text-[11px] text-muted">
                                    {{ $employee->employee_number }}@if ($employee->department) · {{ $employee->department->name }}@endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <p class="hint mb-0">Only the selected employees will see this event.</p>
                    <button type="button" @click="selectedEmployees = []" class="cursor-pointer text-xs font-semibold text-info-700 hover:underline">
                        Clear selection
                    </button>
                </div>
            @endif
            @error('employee_ids') <p class="error-text">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
