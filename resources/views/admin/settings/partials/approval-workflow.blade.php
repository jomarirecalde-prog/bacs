@php
    use App\Enums\ApprovalAssigneeType;
    use App\Enums\ApprovalTransactionType;
    use App\Services\CentralApprovalWorkflowService;

    $defaultDeptId = (int) old('department_id', $approvalDepartments->first()?->id ?? 0);
    $config = $approvalWorkflowConfiguration;
    $workflowService = app(CentralApprovalWorkflowService::class);
@endphp
<div id="approval-workflow" class="scroll-mt-28 card overflow-hidden" x-data="{ dept: @js($defaultDeptId), stage: 'first' }">
    <div class="card-header">
        <div class="min-w-0">
            <h2 class="card-title text-base">Approval workflow configuration</h2>
            <p class="mt-0.5 text-sm text-muted">One workflow per department. The same endorsers and final approvers apply to all request types below.</p>
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
        <nav class="border-b border-line bg-surface-soft/40 px-4 py-3 sm:px-5" aria-label="Department">
            <div class="pill-tabs w-full min-w-0">
                @foreach ($approvalDepartments as $department)
                    <button type="button" @click="dept = @js($department->id); stage = 'first'"
                            class="shrink-0 whitespace-nowrap"
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
            $secondaryEndorsementMode = old('secondary_endorsement_mode', $deptSettings?->secondary_endorsement_mode?->value ?? $endorsementMode);
            $secondaryParallelRule = old('secondary_endorsement_parallel_rule', $deptSettings?->secondary_endorsement_parallel_rule?->value ?? $parallelRule);
            $endorsers = $deptAssignees->where('approval_type', ApprovalAssigneeType::Endorser)->sortBy('sequence_order');
            $secondaryEndorsers = $deptAssignees->where('approval_type', ApprovalAssigneeType::SecondaryEndorser)->sortBy('sequence_order');
            $final = $deptAssignees->where('approval_type', ApprovalAssigneeType::FinalApprover)->first();
            // Existing assignees are submitted via the table rows; Alpine picker only tracks newly added ones.
            $selectedEndorsers = collect([]);
            $selectedSecondaryEndorsers = collect([]);
            // Existing final approver is submitted via hidden input; Alpine picker only tracks a replacement.
            $selectedFinal = collect([]);
            $outOfSync = $workflowService->outOfSyncTransactionTypes($department->id);
            $firstEndorsementSummary = match (true) {
                ! $config->endorsement_enabled => ['label' => 'Skipped', 'state' => 'skip'],
                $endorsers->isEmpty() => ['label' => 'Needs endorsers', 'state' => 'warn'],
                $endorsers->count() === 1 => ['label' => $endorsers->first()->employee?->fullName() ?? '1 endorser', 'state' => 'done'],
                default => ['label' => $endorsers->count().' endorsers', 'state' => 'done'],
            };
            $secondEndorsementSummary = match (true) {
                ! $config->secondary_endorsement_enabled => ['label' => 'Skipped', 'state' => 'skip'],
                $secondaryEndorsers->isEmpty() => ['label' => 'Needs endorsers', 'state' => 'warn'],
                $secondaryEndorsers->count() === 1 => ['label' => $secondaryEndorsers->first()->employee?->fullName() ?? '1 endorser', 'state' => 'done'],
                default => ['label' => $secondaryEndorsers->count().' endorsers', 'state' => 'done'],
            };
            $finalSummary = match (true) {
                ! $config->final_approval_enabled => ['label' => 'Auto-approve', 'state' => 'skip'],
                ! $final => ['label' => 'Not assigned', 'state' => 'warn'],
                default => ['label' => $final->employee?->fullName() ?? 'Assigned', 'state' => 'done'],
            };
        @endphp
        <div x-show="dept === @js($department->id)" x-cloak class="space-y-5 p-4 sm:space-y-6 sm:p-5">
            <div class="employee-context-bar">
                <div class="stat-icon-brand">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-extrabold tracking-tight text-ink">{{ $department->name }}</h3>
                    <p class="mt-0.5 text-sm text-muted">Employees here use these approvers for leave, official time, overtime, pardon, and travel order requests.</p>
                </div>
            </div>

            <div class="workflow-pipeline xl:pointer-events-none" aria-label="Workflow summary">
                <button type="button" class="workflow-pipeline-step {{ $firstEndorsementSummary['state'] === 'warn' ? 'workflow-pipeline-step-warn' : ($firstEndorsementSummary['state'] === 'skip' ? 'workflow-pipeline-step-skip' : 'workflow-pipeline-step-done') }}"
                        :class="stage === 'first' ? 'workflow-pipeline-step-active' : ''"
                        @click="stage = 'first'">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-muted">Step 1</span>
                    <span class="font-bold text-ink">First endorsement</span>
                    <span class="truncate text-xs text-muted" title="{{ $firstEndorsementSummary['label'] }}">{{ $firstEndorsementSummary['label'] }}</span>
                </button>
                <button type="button" class="workflow-pipeline-step {{ $secondEndorsementSummary['state'] === 'warn' ? 'workflow-pipeline-step-warn' : ($secondEndorsementSummary['state'] === 'skip' ? 'workflow-pipeline-step-skip' : 'workflow-pipeline-step-done') }}"
                        :class="stage === 'second' ? 'workflow-pipeline-step-active' : ''"
                        @click="stage = 'second'">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-muted">Step 2</span>
                    <span class="font-bold text-ink">Second endorsement</span>
                    <span class="truncate text-xs text-muted" title="{{ $secondEndorsementSummary['label'] }}">{{ $secondEndorsementSummary['label'] }}</span>
                </button>
                <button type="button" class="workflow-pipeline-step {{ $finalSummary['state'] === 'warn' ? 'workflow-pipeline-step-warn' : ($finalSummary['state'] === 'skip' ? 'workflow-pipeline-step-skip' : 'workflow-pipeline-step-done') }}"
                        :class="stage === 'final' ? 'workflow-pipeline-step-active' : ''"
                        @click="stage = 'final'">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-muted">Step 3</span>
                    <span class="font-bold text-ink">Final approval</span>
                    <span class="truncate text-xs text-muted" title="{{ $finalSummary['label'] }}">{{ $finalSummary['label'] }}</span>
                </button>
            </div>

            <nav class="xl:hidden" aria-label="Edit workflow stage">
                <div class="pill-tabs w-full min-w-0">
                    <button type="button" class="shrink-0 whitespace-nowrap" :class="stage === 'first' ? 'pill-tab-active' : 'pill-tab'" @click="stage = 'first'">1 · First</button>
                    <button type="button" class="shrink-0 whitespace-nowrap" :class="stage === 'second' ? 'pill-tab-active' : 'pill-tab'" @click="stage = 'second'">2 · Second</button>
                    <button type="button" class="shrink-0 whitespace-nowrap" :class="stage === 'final' ? 'pill-tab-active' : 'pill-tab'" @click="stage = 'final'">3 · Final</button>
                </div>
            </nav>

            @if ($outOfSync !== [])
                <div class="alert-warning text-sm">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                    <span>
                        <strong class="font-semibold">Out of sync:</strong>
                        {{ collect($outOfSync)->map(fn (ApprovalTransactionType $type) => $type->label())->join(', ') }} still use different approvers.
                        Save below to unify all five request types.
                    </span>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.approval-workflow.update', 'unified') }}" class="space-y-5 sm:space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="department_id" value="{{ $department->id }}">

                @error('endorser_ids')
                    <div class="alert-danger text-sm">{{ $message }}</div>
                @enderror
                @error('secondary_endorser_ids')
                    <div class="alert-danger text-sm">{{ $message }}</div>
                @enderror
                @error('final_approver_id')
                    <div class="alert-danger text-sm">{{ $message }}</div>
                @enderror

                @if ($config->endorsement_enabled && $endorsers->isEmpty())
                    <div class="alert-warning text-sm">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                        <span>Add at least one <strong>first endorser</strong> in Step 1 before you can save this workflow.</span>
                    </div>
                @endif

                <div class="grid gap-4 xl:grid-cols-3">
                    <div class="workflow-stage-card workflow-stage-card-first" :class="{ '!block': stage === 'first' }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-ink">First endorsement</h3>
                                <p class="text-xs text-muted">After the employee submits a request.</p>
                            </div>
                            <span class="badge-brand shrink-0">Step 1</span>
                        </div>
                        <label class="flex min-h-11 items-center gap-3 rounded-xl border border-line bg-canvas/40 px-3 py-2.5 text-sm">
                            <input type="checkbox" class="checkbox" name="endorsement_enabled" value="1" @checked($config->endorsement_enabled)>
                            <span class="font-semibold text-ink">Enable first endorsement</span>
                        </label>

                        <div class="space-y-2">
                            <span class="label">Endorsement mode</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="workflow-mode-option">
                                    <span class="flex items-center gap-2 font-semibold text-ink">
                                        <input type="radio" name="endorsement_mode" value="parallel" class="shrink-0" @checked($endorsementMode === 'parallel')>
                                        Parallel
                                    </span>
                                    <span class="pl-6 text-xs text-muted">All endorsers act at the same time</span>
                                </label>
                                <label class="workflow-mode-option">
                                    <span class="flex items-center gap-2 font-semibold text-ink">
                                        <input type="radio" name="endorsement_mode" value="sequential" class="shrink-0" @checked($endorsementMode === 'sequential')>
                                        Sequential
                                    </span>
                                    <span class="pl-6 text-xs text-muted">One endorser at a time, in list order</span>
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
                            <div class="relative mt-2">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input x-ref="endorserSearch" type="search" class="input pl-9" placeholder="Search name, position, or department…" x-model="query" @input.debounce.300ms="search" autocomplete="off">
                            </div>
                            @if ($endorsers->isNotEmpty())
                                <div class="table-wrap mt-3 overflow-hidden rounded-xl border border-line">
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
                            <div x-show="open && results.length" x-cloak class="mt-2 max-h-48 overflow-y-auto overscroll-contain rounded-xl border border-line bg-surface shadow-lg">
                                <template x-for="person in results" :key="person.id">
                                    <button type="button" class="block w-full border-b border-line/60 px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-brand-50" @click="add(person)">
                                        <span class="font-semibold" x-text="person.name"></span>
                                        <span class="mt-0.5 block text-xs text-muted" x-text="(person.position || '') + (person.department ? ' · ' + person.department : '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="workflow-stage-card workflow-stage-card-second" :class="{ '!block': stage === 'second' }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-ink">Second endorsement</h3>
                                <p class="text-xs text-muted">Between first endorsement and final approval.</p>
                            </div>
                            <span class="badge-info shrink-0">Step 2</span>
                        </div>
                        <label class="flex min-h-11 items-center gap-3 rounded-xl border border-line bg-canvas/40 px-3 py-2.5 text-sm">
                            <input type="checkbox" class="checkbox" name="secondary_endorsement_enabled" value="1" @checked($config->secondary_endorsement_enabled)>
                            <span class="font-semibold text-ink">Enable second endorsement</span>
                        </label>

                        <div class="space-y-2">
                            <span class="label">Endorsement mode</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="workflow-mode-option">
                                    <span class="flex items-center gap-2 font-semibold text-ink">
                                        <input type="radio" name="secondary_endorsement_mode" value="parallel" class="shrink-0" @checked($secondaryEndorsementMode === 'parallel')>
                                        Parallel
                                    </span>
                                    <span class="pl-6 text-xs text-muted">All second endorsers act together</span>
                                </label>
                                <label class="workflow-mode-option">
                                    <span class="flex items-center gap-2 font-semibold text-ink">
                                        <input type="radio" name="secondary_endorsement_mode" value="sequential" class="shrink-0" @checked($secondaryEndorsementMode === 'sequential')>
                                        Sequential
                                    </span>
                                    <span class="pl-6 text-xs text-muted">One at a time, in list order</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="label">Parallel completion rule</label>
                            <select name="secondary_endorsement_parallel_rule" class="input">
                                @foreach (\App\Enums\LeaveParallelRule::cases() as $rule)
                                    <option value="{{ $rule->value }}" @selected($secondaryParallelRule === $rule->value)>{{ $rule->label() }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-muted">Used when mode is Parallel. Default: all active second endorsers must endorse.</p>
                        </div>

                        <div x-data="travelEmployeePicker({ selected: @js($selectedSecondaryEndorsers), searchUrl: @js($employeeSearchUrl), inputName: 'secondary_endorser_ids' })">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label class="label mb-0">Second endorsers</label>
                                <button type="button" class="btn-outline btn-sm" @click="$refs.secondaryEndorserSearch.focus()">Add endorser</button>
                            </div>
                            <div class="relative mt-2">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input x-ref="secondaryEndorserSearch" type="search" class="input pl-9" placeholder="Search employee…" x-model="query" @input.debounce.300ms="search" autocomplete="off">
                            </div>
                            @if ($secondaryEndorsers->isNotEmpty())
                                <div class="table-wrap mt-3 overflow-hidden rounded-xl border border-line">
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
                            <p class="mt-2 text-xs text-muted">Order follows the list below (first added = first in sequence). Remove and re-add to reorder, or add new endorsers via search.</p>
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
                            <div x-show="open && results.length" x-cloak class="mt-2 max-h-48 overflow-y-auto overscroll-contain rounded-xl border border-line bg-surface shadow-lg">
                                <template x-for="person in results" :key="person.id">
                                    <button type="button" class="block w-full border-b border-line/60 px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-brand-50" @click="add(person)">
                                        <span class="font-semibold" x-text="person.name"></span>
                                        <span class="mt-0.5 block text-xs text-muted" x-text="(person.position || '') + (person.department ? ' · ' + person.department : '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="workflow-stage-card workflow-stage-card-final" :class="{ '!block': stage === 'final' }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-ink">Final approval</h3>
                                <p class="text-xs text-muted">Last decision before the request is approved.</p>
                            </div>
                            <span class="badge-gold shrink-0">Step 3</span>
                        </div>
                        <label class="flex min-h-11 items-center gap-3 rounded-xl border border-line bg-canvas/40 px-3 py-2.5 text-sm">
                            <input type="checkbox" class="checkbox" name="final_approval_enabled" value="1" @checked($config->final_approval_enabled)>
                            <span class="font-semibold text-ink">Enable final approval</span>
                        </label>
                        <div x-data="travelEmployeePicker({ selected: @js($selectedFinal), searchUrl: @js($employeeSearchUrl), single: true, inputName: 'final_approver_id' })">
                            <label class="label" for="final-search-{{ $department->id }}">Department final approver</label>
                            @if ($final)
                                <div class="mb-3 flex items-center gap-3 rounded-xl border border-gold-200 bg-gold-50/70 px-3 py-2.5">
                                    <div class="brand-mark h-10 w-10 shrink-0 text-sm">{{ strtoupper(substr($final->employee?->fullName() ?? '?', 0, 1)) }}</div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-ink">{{ $final->employee?->fullName() }}</p>
                                        <p class="truncate text-xs text-muted">{{ $final->employee?->position ?? 'No position on file' }}</p>
                                    </div>
                                </div>
                                <input type="hidden" name="final_approver_id" value="{{ $final->employee_id }}">
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <template x-for="person in selected" :key="person.id">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-gold-200 bg-gold-50 px-3 py-1 text-sm">
                                        <span x-text="person.name"></span>
                                        <button type="button" class="rounded px-1 text-muted hover:text-critical-700" @click="remove(person.id)" aria-label="Remove approver">&times;</button>
                                        <input type="hidden" :name="inputFieldName()" :value="person.id">
                                    </span>
                                </template>
                            </div>
                            <p class="mt-2 text-xs text-muted">Search below to replace the final approver for this department.</p>
                            <div class="relative mt-2">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input id="final-search-{{ $department->id }}" type="search" class="input pl-9" placeholder="Search to change final approver…" x-model="query" @input.debounce.300ms="search" autocomplete="off">
                            </div>
                            <div x-show="open && results.length" x-cloak class="mt-2 max-h-48 overflow-y-auto overscroll-contain rounded-xl border border-line bg-surface shadow-lg">
                                <template x-for="person in results" :key="person.id">
                                    <button type="button" class="block w-full border-b border-line/60 px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-brand-50" @click="add(person)">
                                        <span class="font-semibold" x-text="person.name"></span>
                                        <span class="mt-0.5 block text-xs text-muted" x-text="(person.position || '') + (person.department ? ' · ' + person.department : '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                @if (session('reassignment_result') && (session('reassignment_result')['total'] ?? 0) > 0)
                    <div class="alert-success text-sm">
                        Last save reassigned {{ session('reassignment_result')['total'] }} task(s) across {{ session('reassignment_result')['transactions'] }} transaction(s).
                    </div>
                @endif

                <div class="sticky bottom-0 z-10 -mx-4 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur-md safe-bottom sm:-mx-5 sm:px-5 xl:static xl:mx-0 xl:border-0 xl:bg-transparent xl:p-0 xl:pt-4">
                    <div class="action-group-stacked">
                        <button type="submit" class="btn-primary w-full sm:w-auto">Save workflow</button>
                        <a href="{{ route('admin.settings.approval-workflow.history', 'unified') }}" class="btn-secondary w-full sm:w-auto">History</a>
                    </div>
                    <p class="mt-2 text-center text-[11px] text-muted xl:text-left">Applies to <span class="font-semibold">{{ $department->name }}</span> only.</p>
                </div>
            </form>
        </div>
    @endforeach
</div>
