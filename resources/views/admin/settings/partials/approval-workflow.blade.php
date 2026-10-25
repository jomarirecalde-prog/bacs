@php
    use App\Enums\ApprovalAssigneeType;
    use App\Enums\ApprovalTransactionType;
    use App\Services\CentralApprovalWorkflowService;

    $defaultDeptId = (int) old('department_id', $approvalDepartments->first()?->id ?? 0);
    $config = $approvalWorkflowConfiguration;
    $workflowService = app(CentralApprovalWorkflowService::class);
@endphp
<div id="approval-workflow" class="mt-6 card overflow-hidden" x-data="{ dept: @js($defaultDeptId) }">
    <div class="card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="card-title">Approval Workflow Configuration</h2>
            <p class="text-sm text-muted">One workflow for every department. The same endorsers and final approvers apply to all request types below.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($approvalTransactionTypes as $transactionType)
                    <span class="inline-flex rounded-full border border-line bg-surface-soft px-3 py-1 text-xs font-semibold text-ink-soft">{{ $transactionType->label() }}</span>
                @endforeach
            </div>
        </div>
    </div>

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

    @foreach ($approvalDepartments as $department)
        @php
            $deptAssignees = $config->assignees->where('department_id', $department->id);
            $deptSettings = $config->departmentSettingFor($department->id);
            $endorsementMode = old('endorsement_mode', $deptSettings?->endorsement_mode?->value ?? 'parallel');
            $parallelRule = old('endorsement_parallel_rule', $deptSettings?->endorsement_parallel_rule?->value ?? 'all');
            $endorsers = $deptAssignees->where('approval_type', ApprovalAssigneeType::Endorser)->sortBy('sequence_order');
            $secondaryEndorsers = $deptAssignees->where('approval_type', ApprovalAssigneeType::SecondaryEndorser)->sortBy('sequence_order');
            $final = $deptAssignees->where('approval_type', ApprovalAssigneeType::FinalApprover)->first();
            // Existing assignees are submitted via the table rows; Alpine picker only tracks newly added ones.
            $selectedEndorsers = collect([]);
            $selectedSecondaryEndorsers = collect([]);
            // Existing final approver is submitted via hidden input; Alpine picker only tracks a replacement.
            $selectedFinal = collect([]);
            $outOfSync = $workflowService->outOfSyncTransactionTypes($department->id);
        @endphp
        <div x-show="dept === @js($department->id)" x-cloak class="space-y-6 p-5">
            @if ($outOfSync !== [])
                <div class="rounded-xl border border-warn-300 bg-warn-50 px-4 py-3 text-sm text-warn-900">
                    Some transaction types still use different approvers:
                    {{ collect($outOfSync)->map(fn (ApprovalTransactionType $type) => $type->label())->join(', ') }}.
                    Save this department to apply the unified workflow to all five types.
                </div>
            @endif

            <div class="rounded-xl border border-line bg-surface-soft/50 px-4 py-3 text-sm">
                <span class="font-semibold text-ink">{{ $department->name }}</span>
                <span class="text-muted"> — employees in this department use these approvers for Leave, Official Time, Overtime, Pardon, and Travel Order.</span>
            </div>

            <form method="POST" action="{{ route('admin.settings.approval-workflow.update', 'unified') }}" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="department_id" value="{{ $department->id }}">
                @foreach ($endorsers as $row)
                    <input type="hidden" name="endorser_ids[]" value="{{ $row->employee_id }}">
                @endforeach
                @foreach ($secondaryEndorsers as $row)
                    <input type="hidden" name="secondary_endorser_ids[]" value="{{ $row->employee_id }}">
                @endforeach
                @if ($final)
                    <input type="hidden" name="final_approver_id" value="{{ $final->employee_id }}">
                @endif

                @error('endorser_ids')
                    <div class="rounded-xl border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900">{{ $message }}</div>
                @enderror
                @error('secondary_endorser_ids')
                    <div class="rounded-xl border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900">{{ $message }}</div>
                @enderror
                @error('final_approver_id')
                    <div class="rounded-xl border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900">{{ $message }}</div>
                @enderror

                @if ($config->endorsement_enabled && $endorsers->isEmpty())
                    <div class="rounded-xl border border-warn-300 bg-warn-50 px-4 py-3 text-sm text-warn-900">
                        <span class="font-semibold">{{ $department->name }}</span> has no first endorsers yet. Add at least one under <strong>First endorsement</strong> before you can save second endorsement or other changes for this department.
                    </div>
                @endif

                <div class="grid gap-4 xl:grid-cols-3">
                    <div class="rounded-2xl border border-line p-4 space-y-4">
                        <h3 class="font-bold text-ink">First endorsement</h3>
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
                                                    <td class="tabular-nums">
                                                        <input type="hidden" name="endorser_ids[]" value="{{ $row->employee_id }}">
                                                        {{ $index + 1 }}
                                                    </td>
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
                        <h3 class="font-bold text-ink">Second endorsement</h3>
                        <p class="text-xs text-muted">Runs after the first endorsement and before final approval. Uses the same endorsement mode and parallel rule above.</p>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="checkbox" name="secondary_endorsement_enabled" value="1" @checked($config->secondary_endorsement_enabled)>
                            <span>Enable second endorsement</span>
                        </label>

                        <div x-data="travelEmployeePicker({ selected: @js($selectedSecondaryEndorsers), searchUrl: @js($employeeSearchUrl), inputName: 'secondary_endorser_ids' })">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label class="label mb-0">Second endorsers</label>
                                <button type="button" class="btn-outline btn-sm" @click="$refs.secondaryEndorserSearch.focus()">Add endorser</button>
                            </div>
                            <input x-ref="secondaryEndorserSearch" type="search" class="input" placeholder="Search employee…" x-model="query" @input.debounce.300ms="search">
                            @if ($secondaryEndorsers->isNotEmpty())
                                <div class="mt-3 overflow-hidden rounded-xl border border-line">
                                    <table class="data-table text-sm">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Endorser</th>
                                                <th>Active</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($secondaryEndorsers as $index => $row)
                                                <tr>
                                                    <td class="tabular-nums">
                                                        <input type="hidden" name="secondary_endorser_ids[]" value="{{ $row->employee_id }}">
                                                        {{ $index + 1 }}
                                                    </td>
                                                    <td class="font-semibold">{{ $row->employee?->fullName() }}</td>
                                                    <td>
                                                        <input type="hidden" name="secondary_endorser_active[{{ $row->employee_id }}]" value="0">
                                                        <input type="checkbox" class="checkbox" name="secondary_endorser_active[{{ $row->employee_id }}]" value="1" @checked($row->is_active)>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="(person, index) in selected" :key="person.id">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-sm">
                                        <span class="text-xs font-bold text-brand-800" x-text="index + 1"></span>
                                        <span x-text="person.name + (person.position ? ' – ' + person.position : '')"></span>
                                        <button type="button" @click="remove(person.id)">&times;</button>
                                        <input type="hidden" :name="inputFieldName()" :value="person.id">
                                        <input type="hidden" :name="'secondary_endorser_active[' + person.id + ']'" value="1">
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
                            @if ($final)
                                <p class="mb-2 text-sm font-semibold text-ink">{{ $final->employee?->fullName() }}</p>
                                <input type="hidden" name="final_approver_id" value="{{ $final->employee_id }}">
                            @endif
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
                                First endorsement ({{ $endorsers->count() }} endorsers)
                            @elseif ($endorsers->count() === 1)
                                First endorsement ({{ $endorsers->first()->employee?->fullName() }})
                            @elseif ($config->endorsement_enabled)
                                <span class="text-warn-700">No first endorsers selected</span>
                            @else
                                First endorsement disabled
                            @endif
                            →
                            @if ($secondaryEndorsers->count() > 0)
                                Second endorsement ({{ $secondaryEndorsers->count() }} endorser{{ $secondaryEndorsers->count() === 1 ? '' : 's' }})
                            @elseif ($config->secondary_endorsement_enabled)
                                <span class="text-warn-700">No second endorsers selected</span>
                            @else
                                Second endorsement skipped
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
                    <a href="{{ route('admin.settings.approval-workflow.history', 'unified') }}" class="btn-secondary">Configuration history</a>
                </div>
            </form>
        </div>
    @endforeach
</div>
