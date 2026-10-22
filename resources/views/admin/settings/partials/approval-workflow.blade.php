@php
    use App\Enums\ApprovalAssigneeType;
    $defaultDeptId = (int) old('department_id', $approvalDepartments->first()?->id ?? 0);
@endphp
<div id="approval-workflow" class="mt-6 card overflow-hidden" x-data="{ tab: @js(old('workflow_tab', $approvalConfigurations->first()?->transaction_type->value ?? 'leave_application')), dept: @js($defaultDeptId) }">
    <div class="card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="card-title">Approval Workflow Configuration</h2>
            <p class="text-sm text-muted">Assign department endorsers and final approvers for Leave, Pardon, Travel Order, Overtime, and Official Time. Requests route only within the employee's department.</p>
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

    @if ($approvalDepartments->isEmpty())
        <div class="p-5 text-sm text-warn-700">Create at least one active department before configuring approval workflows.</div>
    @else
        <nav class="border-b border-line bg-surface-soft/40 px-5 py-3">
            <div class="flex flex-wrap gap-2">
                @foreach ($approvalDepartments as $department)
                    <button type="button" @click="dept = @js($department->id)"
                            :class="dept === @js($department->id) ? 'pill-tab-active' : 'pill-tab'">
                        {{ $department->name }}
                    </button>
                @endforeach
            </div>
        </nav>
    @endif

    @foreach ($approvalConfigurations as $config)
        @foreach ($approvalDepartments as $department)
            @php
                $deptAssignees = $config->assignees->where('department_id', $department->id);
                $deptSettings = $config->departmentSettingFor($department->id);
                $endorsementMode = old('endorsement_mode', $deptSettings?->endorsement_mode?->value ?? 'parallel');
                $parallelRule = old('endorsement_parallel_rule', $deptSettings?->endorsement_parallel_rule?->value ?? 'all');
                $endorsers = $deptAssignees->where('approval_type', ApprovalAssigneeType::Endorser)->sortBy('sequence_order');
                $final = $deptAssignees->where('approval_type', ApprovalAssigneeType::FinalApprover)->first();
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
            <div x-show="tab === @js($config->transaction_type->value) && dept === @js($department->id)" x-cloak class="space-y-6 p-5">
                <div class="rounded-xl border border-line bg-surface-soft/50 px-4 py-3 text-sm">
                    <span class="font-semibold text-ink">{{ $department->name }}</span>
                    <span class="text-muted"> — employees in this department use these approvers for {{ $config->transaction_type->label() }}.</span>
                </div>

                <form method="POST" action="{{ route('admin.settings.approval-workflow.update', $config->transaction_type->value) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="workflow_tab" value="{{ $config->transaction_type->value }}">
                    <input type="hidden" name="department_id" value="{{ $department->id }}">

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-line p-4 space-y-4">
                            <h3 class="font-bold text-ink">Endorsement configuration</h3>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" class="checkbox" name="endorsement_enabled" value="1" @checked($config->endorsement_enabled)>
                                <span>Enable endorsement</span>
                            </label>

                            <div class="space-y-2">
                                <label class="label">Endorsement mode</label>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <label class="flex items-center gap-2 text-sm rounded-xl border border-line px-3 py-2">
                                        <input type="radio" name="endorsement_mode" value="parallel" @checked($endorsementMode === 'parallel')>
                                        <span>Parallel — all endorsers act at the same time</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm rounded-xl border border-line px-3 py-2">
                                        <input type="radio" name="endorsement_mode" value="sequential" @checked($endorsementMode === 'sequential')>
                                        <span>Sequential — one endorser at a time by order</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="label">Parallel completion rule</label>
                                <select name="endorsement_parallel_rule" class="input">
                                    @foreach (\App\Enums\LeaveParallelRule::cases() as $rule)
                                        <option value="{{ $rule->value }}" @selected($parallelRule === $rule->value)>{{ $rule->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-muted">Used when mode is Parallel. Default: all active endorsers must endorse.</p>
                            </div>

                            <div x-data="travelEmployeePicker({ selected: @js($selectedEndorsers), searchUrl: @js($employeeSearchUrl), inputName: 'endorser_ids' })">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label class="label mb-0">Department endorsers</label>
                                    <button type="button" class="btn-outline btn-sm" @click="$refs.endorserSearch.focus()">Add endorser</button>
                                </div>
                                <input x-ref="endorserSearch" type="search" class="input" placeholder="Search employee name, position, or department…" x-model="query" @input.debounce.300ms="search">
                                @if ($endorsers->isNotEmpty())
                                    <div class="mt-3 overflow-hidden rounded-xl border border-line">
                                        <table class="data-table text-sm">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Endorser</th>
                                                    <th>Department</th>
                                                    <th>Designation</th>
                                                    <th>Active</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($endorsers as $index => $row)
                                                    <tr>
                                                        <td class="tabular-nums">{{ $index + 1 }}</td>
                                                        <td class="font-semibold">{{ $row->employee?->fullName() }}</td>
                                                        <td>{{ $row->employee?->department?->name ?? $department->name }}</td>
                                                        <td>{{ $row->employee?->position ?? '—' }}</td>
                                                        <td>
                                                            <input type="hidden" name="endorser_active[{{ $row->employee_id }}]" value="0">
                                                            <input type="checkbox" class="checkbox" name="endorser_active[{{ $row->employee_id }}]" value="1" @checked($row->is_active)>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                                <p class="mt-2 text-xs text-muted">Order follows the list below (first added = first in sequence). Remove and re-add to reorder, or add new endorsers via search.</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="(person, index) in selected" :key="person.id">
                                        <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-sm">
                                            <span class="text-xs font-bold text-brand-800" x-text="index + 1"></span>
                                            <span x-text="person.name + (person.position ? ' – ' + person.position : '')"></span>
                                            <button type="button" @click="remove(person.id)">&times;</button>
                                            <input type="hidden" :name="inputFieldName()" :value="person.id">
                                            <input type="hidden" :name="'endorser_active[' + person.id + ']'" value="1">
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
                                <label class="label">Department final approver</label>
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
                        <div class="font-bold text-gold-900">Workflow preview ({{ $department->name }})</div>
                        <div class="mt-2 space-y-1 text-ink-soft">
                            <div>Requester →
                                @if ($endorsers->count() > 1)
                                    Parallel Endorsement ({{ $endorsers->count() }} endorsers, all must endorse)
                                @elseif ($endorsers->count() === 1)
                                    Single Endorsement ({{ $endorsers->first()->employee?->fullName() }})
                                @elseif ($config->endorsement_enabled)
                                    <span class="text-warn-700">No endorsers selected for this department</span>
                                @else
                                    Endorsement disabled
                                @endif
                                →
                                @if ($config->final_approval_enabled && $final)
                                    Final Approval ({{ $final->employee?->fullName() }}) → Approved
                                @elseif ($config->final_approval_enabled)
                                    <span class="text-warn-700">Final approver not selected for this department</span>
                                @else
                                    Auto-complete after endorsement
                                @endif
                            </div>
                        </div>
                    </div>

                    @if (session('reassignment_result') && (session('reassignment_result')['total'] ?? 0) > 0)
                        <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
                            Last save reassigned {{ session('reassignment_result')['total'] }} task(s) across {{ session('reassignment_result')['transactions'] }} transaction(s).
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="btn-primary">Save {{ $department->name }} workflow</button>
                        <a href="{{ route('admin.settings.approval-workflow.history', $config->transaction_type->value) }}" class="btn-secondary">Configuration history</a>
                    </div>
                </form>
            </div>
        @endforeach
    @endforeach
</div>
