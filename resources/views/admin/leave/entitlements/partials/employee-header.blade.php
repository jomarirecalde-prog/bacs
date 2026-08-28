<div class="card card-accent-brand">
    <div class="card-body grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-muted">Employee</p>
            <p class="font-semibold">{{ $employee->fullName() }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-muted">Employee ID</p>
            <p class="font-semibold tabular-nums">{{ $employee->employee_number }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-muted">Department</p>
            <p class="font-semibold">{{ $employee->department?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-muted">Position</p>
            <p class="font-semibold">{{ $employee->position ?? '—' }}</p>
        </div>
    </div>
</div>
