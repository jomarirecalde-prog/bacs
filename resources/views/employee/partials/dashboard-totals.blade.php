<section class="card w-full min-w-0 overflow-hidden shadow-soft" aria-labelledby="totals-heading">
    <div class="flex items-center justify-between border-b border-line px-3 py-2">
        <h3 id="totals-heading" class="text-xs font-bold uppercase tracking-wide text-muted">Today's totals</h3>
        <x-status-badge :status="$today?->status" class="!py-0.5" />
    </div>
    <dl class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-4">
        <div class="rounded-lg border border-line bg-canvas/50 px-2.5 py-2">
            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">Hours</dt>
            <dd class="text-lg font-extrabold tabular-nums text-ink">{{ $today?->totalHoursLabel() ?? '0:00' }}</dd>
        </div>
        <div class="rounded-lg border border-line bg-canvas/50 px-2.5 py-2">
            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">Late</dt>
            <dd @class(['text-lg font-extrabold tabular-nums', 'text-warn-700' => $lateMinutes > 0, 'text-ink' => $lateMinutes <= 0])>{{ $lateMinutes }}<span class="text-xs font-semibold text-muted"> m</span></dd>
        </div>
        <div class="rounded-lg border border-line bg-canvas/50 px-2.5 py-2">
            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">UT</dt>
            <dd @class(['text-lg font-extrabold tabular-nums', 'text-warn-700' => $undertimeMinutes > 0, 'text-ink' => $undertimeMinutes <= 0])>{{ $undertimeMinutes }}<span class="text-xs font-semibold text-muted"> m</span></dd>
        </div>
        <div class="rounded-lg border border-line bg-canvas/50 px-2.5 py-2">
            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">OT</dt>
            <dd @class(['text-lg font-extrabold tabular-nums', 'text-gold-700' => $overtimeMinutes > 0, 'text-ink' => $overtimeMinutes <= 0])>{{ $today?->overtimeHoursLabel() ?? '0:00' }}</dd>
        </div>
    </dl>
</section>
