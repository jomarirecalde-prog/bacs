@php
    use App\Enums\AttendanceCorrectionStatus;
    use App\Enums\EndorsementMode;
    use App\Enums\LeaveParallelRule;
    use App\Enums\LeaveStatus;
    use App\Enums\TravelOrderStatus;
    use App\Models\AttendanceCorrectionRequest;
    use App\Models\LeaveApplication;
    use App\Models\TravelOrder;

    $stageIcons = [
        'done' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'wait' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'deny' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];

    $subject = $subject ?? null;
    $centralLabels = $subject->approvalTimelineUsesCentralLabels();
    $stages = $subject->approvalTimelineStages();
    $endorsementMode = $subject->snapshotEndorsementMode();
    $parallelRule = $subject->snapshotParallelRule();
    $stageCount = count($stages);
@endphp

<ol class="relative space-y-0">
    <li class="relative flex gap-3 pb-6">
        @if ($stageCount > 0 || $stages === [])
            <span class="absolute left-4 top-8 bottom-0 w-px bg-line" aria-hidden="true"></span>
        @endif
        <span class="relative z-[1] stat-icon-brand h-8 w-8 shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['done'] }}"/></svg>
        </span>
        <div class="min-w-0 flex-1 pt-0.5">
            <div class="text-sm font-bold text-ink">Submitted</div>
            <div class="text-xs text-muted">{{ $submittedDetail ?? '—' }}</div>
        </div>
    </li>

    @if ($stages === [])
        <li class="relative flex gap-3 pb-2">
            <span class="relative z-[1] stat-icon-warn h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['wait'] }}"/></svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="text-sm font-bold text-ink">Approvers</div>
                <div class="text-xs text-muted">{{ $emptyApproversMessage ?? 'No endorsers or final approver were assigned from Settings → Approval Workflow when this was submitted.' }}</div>
            </div>
        </li>
    @endif

    @foreach ($stages as $index => $stage)
        @php
            $rows = $subject->assignmentsFor($stage);
            $isCurrent = $subject->current_stage === $stage && $subject->status?->isOpen();
            $decision = $subject->stageDecision($stage);
            $isLastStage = $index === $stageCount - 1;
            $tone = $decision === 'denied' ? 'critical' : ($decision === 'approved' || $decision === 'mixed' || $decision === 'skipped' ? 'brand' : ($isCurrent ? 'warn' : 'info'));
            $showEndorsementMeta = $centralLabels && $stage->usesCentralEndorsementRules();
        @endphp
        <li class="relative flex gap-3 {{ $isLastStage ? 'pb-0' : 'pb-6' }}">
            @if (! $isLastStage)
                <span class="absolute left-4 top-8 bottom-0 w-px bg-line" aria-hidden="true"></span>
            @endif
            <span class="relative z-[1] stat-icon-{{ $tone === 'critical' ? 'critical' : ($tone === 'warn' ? 'warn' : ($tone === 'brand' ? 'brand' : 'info')) }} h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $decision === 'denied' ? $stageIcons['deny'] : ($decision ? $stageIcons['done'] : $stageIcons['wait']) }}"/></svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md bg-surface-soft px-1.5 text-[10px] font-bold tabular-nums text-muted">{{ $index + 1 }}</span>
                    <div class="text-sm font-bold text-ink">{{ $stage->timelineLabel($centralLabels) }}</div>
                    @if ($showEndorsementMeta)
                        <span class="badge-neutral">{{ $endorsementMode->timelineBadge() }}</span>
                    @endif
                    @if ($stage->isFinalApproval())
                        <span class="badge-gold">CEO decision</span>
                    @endif
                    @if ($isCurrent)
                        <span class="badge-warn">In progress</span>
                    @elseif ($decision === 'approved' || $decision === 'mixed')
                        <span class="badge-brand">Complete</span>
                    @elseif ($decision === 'denied')
                        <span class="badge-critical">Stopped</span>
                    @endif
                </div>
                @if ($showEndorsementMeta)
                    <p class="mt-1 text-xs text-muted">
                        @if ($endorsementMode === EndorsementMode::Sequential)
                            Endorsers act in order; the next person is notified after the prior endorsement.
                        @else
                            {{ $parallelRule->timelineHint() }}.
                        @endif
                    </p>
                @endif

                @if ($rows->isEmpty())
                    <div class="mt-2 text-xs text-muted">No approver assigned — stage skipped.</div>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($rows as $row)
                            @php
                                $statusLabel = $row->decisionLabel();
                                if ($stage->isFinalApproval()) {
                                    $statusLabel = match (true) {
                                        $row->isApproved() => 'Approved',
                                        $row->isDenied() => 'Denied',
                                        default => $statusLabel,
                                    };
                                }
                            @endphp
                            <li @class([
                                'rounded-xl border px-3 py-2.5 text-sm',
                                'border-warn-300 bg-warn-50/80' => $isCurrent && $row->isPending(),
                                'border-line bg-canvas/70' => ! ($isCurrent && $row->isPending()),
                            ])>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink">{{ $row->approver_name }}</div>
                                        @if ($row->approver_position)
                                            <div class="text-xs text-muted">{{ $row->approver_position }}</div>
                                        @endif
                                    </div>
                                    <span @class([
                                        'shrink-0',
                                        $row->isApproved() ? 'badge-brand' : ($row->isDenied() ? 'badge-critical' : ($row->status === 'skipped' ? 'badge-neutral' : 'badge-warn')),
                                    ])>{{ $statusLabel }}</span>
                                </div>
                                @if ($row->acted_at)
                                    <div class="mt-1.5 text-xs text-muted">{{ $row->actedAtLabel() }}@if ($row->reason) · {{ $row->reason }}@endif</div>
                                @elseif ($row->status === 'waiting')
                                    <div class="mt-1.5 text-xs text-muted">Waiting for prior endorser in sequence</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </li>
    @endforeach

    @if ($subject instanceof LeaveApplication && $subject->status === LeaveStatus::Cancelled)
        <li class="relative flex gap-3 pt-2">
            <span class="relative z-[1] stat-icon-critical h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['deny'] }}"/></svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="text-sm font-bold text-ink">Cancelled by employee</div>
                <div class="text-xs text-muted">{{ $subject->cancelled_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} @if ($subject->cancel_reason)· {{ $subject->cancel_reason }}@endif</div>
            </div>
        </li>
    @elseif ($subject instanceof TravelOrder && $subject->status === TravelOrderStatus::Cancelled)
        <li class="relative flex gap-3 pt-2">
            <span class="relative z-[1] stat-icon-critical h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['deny'] }}"/></svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="text-sm font-bold text-ink">Cancelled</div>
                <div class="text-xs text-muted">{{ $subject->cancelled_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} @if ($subject->cancel_reason)· {{ $subject->cancel_reason }}@endif</div>
            </div>
        </li>
    @elseif ($subject instanceof AttendanceCorrectionRequest && $subject->status === AttendanceCorrectionStatus::Cancelled)
        <li class="relative flex gap-3 pt-2">
            <span class="relative z-[1] stat-icon-critical h-8 w-8 shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stageIcons['deny'] }}"/></svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="text-sm font-bold text-ink">Cancelled</div>
                <div class="text-xs text-muted">This correction request was cancelled by the employee.</div>
            </div>
        </li>
    @endif
</ol>
