@php
    $stageIcons = [
        'done' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'wait' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'deny' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    $request = $correction;
    $centralLabels = $request->approvalTimelineUsesCentralLabels();
    $stages = $request->approvalTimelineStages();
@endphp
<ol class="space-y-4">
    <li class="flex gap-3">
        <span class="stat-icon-brand h-8 w-8 shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['done'] }}"/></svg>
        </span>
        <div>
            <div class="text-sm font-bold text-ink">Submitted</div>
            <div class="text-xs text-muted">{{ $request->attendance_date?->format('M j, Y') }} · {{ $request->employee?->fullName() }}</div>
        </div>
    </li>

    @if ($stages === [])
        <li class="flex gap-3">
            <span class="stat-icon-warn h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['wait'] }}"/></svg>
            </span>
            <div>
                <div class="text-sm font-bold text-ink">Approvers</div>
                <div class="text-xs text-muted">Awaiting review under Settings → Approval Workflow (Pardon / Time Correction).</div>
            </div>
        </li>
    @endif

    @foreach ($stages as $stage)
        @php
            $rows = $request->assignmentsFor($stage);
            $isCurrent = $request->current_stage === $stage && $request->status?->isOpen();
            $decision = $request->stageDecision($stage);
        @endphp
        <li class="flex gap-3">
            @php
                $tone = $decision === 'denied' ? 'critical' : ($decision === 'approved' || $decision === 'mixed' || $decision === 'skipped' ? 'brand' : ($isCurrent ? 'warn' : 'info'));
            @endphp
            <span class="stat-icon-{{ $tone === 'critical' ? 'critical' : ($tone === 'warn' ? 'warn' : ($tone === 'brand' ? 'brand' : 'info')) }} h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $decision === 'denied' ? $stageIcons['deny'] : ($decision ? $stageIcons['done'] : $stageIcons['wait']) }}"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="text-sm font-bold text-ink">{{ $stage->timelineLabel($centralLabels) }}</div>
                    @if ($stage->isParallel())<span class="badge-gold">Parallel endorsement</span>@endif
                    @if ($stage->isFinalApproval())<span class="badge-gold">Final approval</span>@endif
                    @if ($isCurrent)<span class="badge-warn">Current</span>@endif
                </div>
                @if ($rows->isEmpty())
                    <div class="text-xs text-muted">No approver assigned — stage skipped.</div>
                @else
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($rows as $row)
                            <li class="rounded-xl border border-line bg-canvas/70 px-3 py-2 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-semibold text-ink">{{ $row->approver_name }}</span>
                                        <span class="text-xs text-muted">{{ $row->approver_position }}</span>
                                    </div>
                                    <span class="{{ $row->isApproved() ? 'badge-brand' : ($row->isDenied() ? 'badge-critical' : 'badge-warn') }}">
                                        {{ $row->decisionLabel() }}
                                    </span>
                                </div>
                                @if ($row->acted_at)
                                    <div class="mt-1 text-xs text-muted">{{ $row->actedAtLabel() }}@if ($row->reason) · {{ $row->reason }}@endif</div>
                                @elseif ($isCurrent)
                                    <div class="mt-1 text-xs text-warn-700">Pending</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </li>
    @endforeach

    @if ($request->status === \App\Enums\AttendanceCorrectionStatus::Cancelled)
        <li class="flex gap-3">
            <span class="stat-icon-critical h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['deny'] }}"/></svg>
            </span>
            <div>
                <div class="text-sm font-bold text-ink">Cancelled</div>
                <div class="text-xs text-muted">This correction request was cancelled by the employee.</div>
            </div>
        </li>
    @endif
</ol>
