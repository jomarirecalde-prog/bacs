@extends('layouts.app')

@section('title', 'Edit Leave Balance — '.$employee->fullName())
@section('page-title', 'Edit Employee Leave Balance')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="space-y-6" x-data="leaveBalanceAdjust({
    previewUrl: @js(route('admin.leave.entitlements.adjustments.preview', ['employee' => $employee, 'year' => $year])),
    csrf: @js(csrf_token()),
})">
    @include('admin.leave.entitlements.partials.employee-header', ['employee' => $employee, 'year' => $year])

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">{{ $year }} current balances</h2>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Leave type</th>
                            <th>Entitled</th>
                            <th>Taken</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $type)
                            @php $b = $balances[$type->code] ?? null; @endphp
                            <tr>
                                <td class="font-semibold">{{ $type->name }}</td>
                                <td class="tabular-nums">{{ $b ? rtrim(rtrim(number_format($b['entitled'], 1), '0'), '.') : '0' }}</td>
                                <td class="tabular-nums">{{ $b ? rtrim(rtrim(number_format($b['used'], 1), '0'), '.') : '0' }}</td>
                                <td class="tabular-nums font-semibold">{{ $b ? rtrim(rtrim(number_format($b['remaining'], 1), '0'), '.') : '0' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.leave.entitlements.adjustments.store', ['employee' => $employee, 'year' => $year]) }}" class="card card-accent-gold">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <div class="card-header">
                <h2 class="card-title">Manual balance adjustment</h2>
                <p class="text-xs text-muted">All adjustments are audited and cannot be silently overwritten.</p>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="label" for="leave_type_code">Leave type</label>
                    <select id="leave_type_code" name="leave_type_code" class="select" x-model="leaveTypeCode" @change="clearPreview()" required>
                        @foreach ($types as $type)
                            <option value="{{ $type->code }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="adjustment_kind">Adjustment type</label>
                    <select id="adjustment_kind" name="adjustment_kind" class="select" x-model="adjustmentKind" @change="clearPreview()" required>
                        <option value="add">Add days</option>
                        <option value="deduct">Deduct days</option>
                        <option value="set_entitlement">Set / correct entitlement</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="days">Number of days</label>
                    <input id="days" type="number" name="days" class="input" min="0" max="365" step="0.5" x-model="days" @input="clearPreview()" required>
                </div>
                <div>
                    <label class="label" for="effective_date">Effective date</label>
                    <input id="effective_date" type="date" name="effective_date" class="input" value="{{ old('effective_date', now()->toDateString()) }}" required>
                </div>
                <div>
                    <label class="label" for="authorized_by_name">Authorized by</label>
                    <input id="authorized_by_name" type="text" name="authorized_by_name" class="input" value="{{ old('authorized_by_name', auth()->user()->name) }}" required>
                </div>
                <div>
                    <label class="label" for="reason">Reason for adjustment</label>
                    <textarea id="reason" name="reason" class="input min-h-24" required minlength="3">{{ old('reason') }}</textarea>
                </div>

                <div class="rounded-lg border border-border bg-surface-50 p-4 space-y-2" x-show="preview" x-cloak>
                    <p class="text-sm font-semibold">Adjustment preview</p>
                    <p class="text-sm">Current balance: <span class="font-semibold tabular-nums" x-text="formatDays(preview?.previous_balance)"></span> days</p>
                    <p class="text-sm">Adjustment: <span class="font-semibold tabular-nums" x-text="formatAdjustment(preview?.adjustment_days)"></span></p>
                    <p class="text-sm">New balance: <span class="font-semibold tabular-nums text-brand-700" x-text="formatDays(preview?.new_balance)"></span> days</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary" @click="loadPreview()" :disabled="loading">Preview adjustment</button>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirm" value="1" class="mt-1" {{ old('confirm') ? 'checked' : '' }} required>
                    <span>I confirm this adjustment is authorized and accurate.</span>
                </label>
            </div>
            <div class="card-footer flex flex-wrap gap-2">
                <button type="submit" class="btn-primary">Apply adjustment</button>
                <a href="{{ route('admin.leave.entitlements.show', ['employee' => $employee, 'year' => $year]) }}" class="btn-outline">Cancel</a>
            </div>
        </form>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.leave.entitlements') }}" class="btn-outline">Back to list</a>
        <a href="{{ route('admin.leave.entitlements.adjustments', ['employee' => $employee, 'year' => $year]) }}" class="btn-secondary">Adjustment history</a>
        <a href="{{ route('admin.leave.entitlements.leave-history', $employee) }}" class="btn-secondary">Leave history</a>
    </div>
</div>
@endsection
