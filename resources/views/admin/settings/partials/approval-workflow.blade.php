@php
    use App\Enums\ApprovalAssigneeType;
@endphp
<div id="approval-workflow" class="mt-6 card overflow-hidden" x-data="{ tab: @js(old('workflow_tab', $approvalConfigurations->first()?->transaction_type->value ?? 'leave_application')) }">
    <div class="card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="card-title">Approval Workflow Configuration</h2>
            <p class="text-sm text-muted">Configure endorsers and final approvers for Leave, Pardon, Travel Order, Overtime, and Official Time.</p>
        </div>
    </div>

    <nav class="border-b border-line px-5 pt-4">
        <div class="pill-tabs">
            @foreach ($approvalConfigurations as $config)
                <button type="button" @click="tab = @js($config->transaction_type->value)"
                        :class="tab === @js($config->transaction_type->value) ? 'pill-tab-active' : 'pill-tab'">
                    {{ $config->transaction_type->label() }}
                </button>
            @endforeach
        </div>
    </nav>

    @foreach ($approvalConfigurations as $config)
        @php
            $endorsers = $config->assignees->where('approval_type', ApprovalAssigneeType::Endorser);
            $final = $config->assignees->where('approval_type', ApprovalAssigneeType::FinalApprover)->first();
            $selectedEndorsers = $endorsers->map(fn ($row) => [
                'id' => $row->employee_id,
                'name' => $row->employee?->fullName(),
                'position' => $row->employee?->position,
                'department' => $row->employee?->department?->name,
            ])->values();
            $selectedFinal = $final ? [[
                'id' => $final->employee_id,
                'name' => $final->employee?->fullName(),
                'position' => $final->employee?->position,
                'department' => $final->employee?->department?->name,
            ]] : [];
        @endphp
        <div x-show="tab === @js($config->transaction_type->value)" x-cloak class="space-y-6 p-5">
            <form method="POST" action="{{ route('admin.settings.approval-workflow.update', $config->transaction_type->value) }}" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="workflow_tab" value="{{ $config->transaction_type->value }}">

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border border-line p-4 space-y-4">
                        <h3 class="font-bold text-ink">Endorsement configuration</h3>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="checkbox" name="endorsement_enabled" value="1" @checked($config->endorsement_enabled)>
                            <span>Enable endorsement</span>
                        </label>
                        <div x-data="travelEmployeePicker({ selected: @js($selectedEndorsers), searchUrl: @js($employeeSearchUrl), inputName: 'endorser_ids' })">
                            <label class="label">Select endorsers</label>
                            <input type="search" class="input" placeholder="Search employee name, position, or department…" x-model="query" @input.debounce.300ms="search">
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="person in selected" :key="person.id">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-sm">
                                        <span x-text="person.name + (person.position ? ' – ' + person.position : '')"></span>
                                        <button type="button" @click="remove(person.id)">&times;</button>
                                        <input type="hidden" :name="inputFieldName()" :value="person.id">
                                    </span>
                                </template>
                            </div>
                            <div x-show="open && results.length" x-cloak class="mt-2 rounded-xl border border-line bg-surface shadow-lg">
                                <template x-for="person in results" :key="person.id">
                                    <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50" @click="add(person)">
                                        <span class="font-semibold" x-text="person.name"></span>
                                        <span class="text-xs text-muted" x-text="(person.position || '') + (person.department ? ' · ' + person.department : '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-line p-4 space-y-4">
                        <h3 class="font-bold text-ink">Final approval</h3>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="checkbox" name="final_approval_enabled" value="1" @checked($config->final_approval_enabled)>
                            <span>Enable final approval</span>
                        </label>
                        <div x-data="travelEmployeePicker({ selected: @js($selectedFinal), searchUrl: @js($employeeSearchUrl), single: true, inputName: 'final_approver_id' })">
                            <label class="label">Final approver</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="person in selected" :key="person.id">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-gold-200 bg-gold-50 px-3 py-1 text-sm">
                                        <span x-text="person.name"></span>
                                        <button type="button" @click="remove(person.id)">&times;</button>
                                        <input type="hidden" :name="inputFieldName()" :value="person.id">
                                    </span>
                                </template>
                            </div>
                            <input type="search" class="input mt-2" placeholder="Search employee…" x-model="query" @input.debounce.300ms="search">
                            <div x-show="open && results.length" x-cloak class="mt-2 rounded-xl border border-line bg-surface shadow-lg">
                                <template x-for="person in results" :key="person.id">
                                    <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50" @click="add(person)">
                                        <span class="font-semibold" x-text="person.name"></span>
                                        <span class="text-xs text-muted" x-text="(person.position || '') + (person.department ? ' · ' + person.department : '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gold-200 bg-gold-50/70 p-4 text-sm">
                    <div class="font-bold text-gold-900">Workflow preview</div>
                    <div class="mt-2 space-y-1 text-ink-soft">
                        <div>Requester →
                            @if ($endorsers->count() > 1)
                                Parallel Endorsement ({{ $endorsers->count() }} endorsers, all must endorse)
                            @elseif ($endorsers->count() === 1)
                                Single Endorsement ({{ $endorsers->first()->employee?->fullName() }})
                            @elseif ($config->endorsement_enabled)
                                <span class="text-warn-700">No endorsers selected</span>
                            @else
                                Endorsement disabled
                            @endif
                            →
                            @if ($config->final_approval_enabled && $final)
                                Final Approval ({{ $final->employee?->fullName() }}) → Approved
                            @elseif ($config->final_approval_enabled)
                                <span class="text-warn-700">Final approver not selected</span>
                            @else
                                Auto-complete after endorsement
                            @endif
                        </div>
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" class="checkbox mt-0.5" name="confirm_auto_approval" value="1">
                    <span>I confirm automatic approval when both endorsement and final approval are disabled.</span>
                </label>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-primary">Save workflow</button>
                    <a href="{{ route('admin.settings.approval-workflow.history', $config->transaction_type->value) }}" class="btn-secondary">View history</a>
                </div>
            </form>
        </div>
    @endforeach
</div>
