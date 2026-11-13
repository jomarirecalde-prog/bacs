<div class="card mt-6">
    <div class="card-header"><h2 class="card-title">Activities</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Activities</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration</th>
                    <th>Status / Remark</th>
                    <th>Reviewed / Vouched</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report->activities as $index => $activity)
                    <tr>
                        <td class="tabular-nums">{{ $index + 1 }}</td>
                        <td>{{ $activity->activity_description }}</td>
                        <td>{{ $activity->activity_date?->format('M j, Y') }}</td>
                        <td class="tabular-nums">{{ $activity->timeRangeLabel() }}</td>
                        <td>{{ $activity->durationLabel() }}</td>
                        <td>{{ $activity->status_remark ?? '—' }}</td>
                        <td>{{ $activity->reviewed_vouched_by ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No activities recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
