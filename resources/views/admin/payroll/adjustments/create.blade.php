@extends('layouts.app')

@section('title', 'New Adjustment')
@section('page-title', 'New payroll adjustment')
@section('page-subtitle', $period->period_name)

@section('content')
<div class="card max-w-xl overflow-hidden">
    <form method="POST" action="{{ route('admin.payroll.adjustments.store', $period) }}" class="space-y-4 p-5">
        @csrf
        <div>
            <label class="label" for="employee_id">Employee ID</label>
            <input id="employee_id" class="input" type="number" name="employee_id" required>
            <p class="hint mt-1">Internal employee record ID (from employee profile URL).</p>
        </div>
        <div>
            <label class="label" for="adjustment_type">Adjustment type</label>
            <input id="adjustment_type" class="input" name="adjustment_type" placeholder="e.g. Time correction retro" required>
        </div>
        <div>
            <label class="label" for="direction">Direction</label>
            <select id="direction" class="select" name="direction">
                <option value="earning">Earning (+)</option>
                <option value="deduction">Deduction (−)</option>
            </select>
        </div>
        <div>
            <label class="label" for="adjustment_amount">Amount (₱)</label>
            <input id="adjustment_amount" class="input" type="number" step="0.01" min="0.01" name="adjustment_amount" required>
        </div>
        <div>
            <label class="label" for="reason">Reason</label>
            <textarea id="reason" class="textarea" name="reason" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn-primary">Save adjustment</button>
    </form>
</div>
@endsection
