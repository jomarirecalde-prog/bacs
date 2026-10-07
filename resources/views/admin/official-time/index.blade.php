@extends('layouts.app')
@section('title', 'Official Time Management')
@section('page-title', 'Official Time Management')

@section('content')
<form method="GET" class="card mb-4">
    <div class="card-body grid gap-3 md:grid-cols-4">
        <input class="input" name="request_no" placeholder="Request number" value="{{ $filters['request_no'] ?? '' }}">
        <input class="input" name="employee" placeholder="Employee name / number" value="{{ $filters['employee'] ?? '' }}">
        <select class="select" name="department_id">
            <option value="">All departments</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
        <select class="select" name="designation_id">
            <option value="">All designations</option>
            @foreach ($designations as $des)
                <option value="{{ $des->id }}" @selected(($filters['designation_id'] ?? '') == $des->id)>{{ $des->designation_name }}</option>
            @endforeach
        </select>
        <select class="select" name="official_time_type_id">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected(($filters['official_time_type_id'] ?? '') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select class="select" name="status">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <input type="date" class="input" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        <input type="date" class="input" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        <div class="md:col-span-4"><button class="btn-primary">Apply filters</button></div>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Request No.</th><th>Employee</th><th>Date</th><th>Type</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($requests as $row)
                    <tr>
                        <td class="font-semibold">{{ $row->request_no }}</td>
                        <td>{{ $row->employee?->fullName() }}</td>
                        <td>{{ $row->date?->format('M j, Y') }}</td>
                        <td>{{ $row->officialTimeType?->name }}</td>
                        <td><span class="{{ $row->status->badgeClass() }}">{{ $row->status->label() }}</span></td>
                        <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.official-time.show', $row) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-empty-state title="No requests" message="No Official Time requests match your filters." icon="document" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
</div>
@endsection
