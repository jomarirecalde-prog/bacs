@props([
    'label',
    'multiple' => false,
    'selected' => [],
    'name',
])

<div
    class="space-y-2"
    x-data="approverPicker({
        name: @js($name),
        multiple: @js($multiple),
        selected: @js($selected),
        searchUrl: @js(route('admin.leave.workflow.employees.search')),
    })"
>
    <label class="label">{{ $label }}@if ($multiple) <span class="text-muted">(multiple, parallel)</span>@endif</label>

    <div class="flex flex-wrap gap-2">
        <template x-for="person in selected" :key="person.id">
            <span class="inline-flex items-center gap-1 rounded-full border border-line bg-white px-3 py-1 text-sm">
                <span x-text="person.name"></span>
                <button type="button" class="text-muted hover:text-critical-600" @click="remove(person.id)" aria-label="Remove">&times;</button>
                <input type="hidden" :name="inputName()" :value="person.id">
            </span>
        </template>
    </div>

    <div class="relative">
        <input
            type="search"
            class="input"
            placeholder="Search employees by name, ID, department, or position"
            x-model="query"
            @input.debounce.300ms="search()"
            @focus="open = true"
            autocomplete="off"
        >
        <div
            x-show="open && results.length"
            x-cloak
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-line bg-white shadow-soft"
            @click.outside="open = false"
        >
            <template x-for="result in results" :key="result.id">
                <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm hover:bg-canvas"
                    @click="add(result)"
                >
                    <div class="font-semibold text-ink" x-text="result.name"></div>
                    <div class="text-xs text-muted">
                        <span x-text="result.position || '—'"></span>
                        · <span x-text="result.department || '—'"></span>
                        · <span x-text="result.role"></span>
                    </div>
                </button>
            </template>
        </div>
    </div>
    @if ($multiple)
        <p class="hint">Add multiple supervisors for parallel approval at this stage.</p>
    @endif
</div>
