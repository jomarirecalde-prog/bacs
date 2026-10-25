<div class="card overflow-hidden">
    <div class="card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="card-title">Manual salary computation</h2>
            <p class="mt-0.5 text-xs text-muted">Prepare period-based or monthly computations with audit trail and duplicate protection.</p>
        </div>
        <a href="{{ route('admin.payroll.employees.manual-computations.create', $employee) }}" class="btn-primary btn-sm">New computation</a>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table w-full text-sm">
            <thead>
                <tr>
                    <th>Coverage</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-right">Net pay</th>
                    <th>Prepared</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($manualComputations as $row)
                    <tr>
                        <td>
                            {{ $row->coverage_start->format('M j, Y') }} – {{ $row->coverage_end->format('M j, Y') }}
                            @if ($row->payrollPeriod)
                                <span class="block text-xs text-muted">{{ $row->payrollPeriod->period_name }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-ink-soft">{{ $row->computation_mode?->label() }}</span>
                            @if ($row->monthly_subtype)
                                <span class="block text-xs text-muted">{{ $row->monthly_subtype->label() }}</span>
                            @endif
                        </td>
                        <td><span class="badge-brand">{{ $row->status?->label() }}</span></td>
                        <td class="text-right tabular-nums font-semibold">₱{{ number_format($row->net_pay, 2) }}</td>
                        <td class="text-muted text-xs">
                            {{ $row->preparedByUser?->name ?? '—' }}
                            @if ($row->prepared_at)
                                <span class="block">{{ $row->prepared_at->format('M j, Y g:i A') }}</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.payroll.employees.manual-computations.show', [$employee, $row]) }}" class="btn-outline btn-sm">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-0">
                            <x-empty-state title="No manual computations yet" message="Start a period-based or monthly computation for this employee." icon="document" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
