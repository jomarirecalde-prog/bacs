@extends('layouts.app')

@section('title', 'New Payroll Period')
@section('page-title', 'New payroll period')
@section('page-subtitle', 'Use the same cut-off window as official DTR')

@section('content')
<div class="card card-accent-brand max-w-xl overflow-hidden">
    <form method="POST" action="{{ route('admin.payroll.periods.store') }}" class="space-y-4 p-5">
        @csrf
        <div>
            <label class="label" for="period_name">Period name</label>
            <input id="period_name" class="input" name="period_name" value="{{ old('period_name', $suggested['period_name']) }}" required>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="start_date">Start date</label>
                <input id="start_date" type="date" class="input" name="start_date" value="{{ old('start_date', $suggested['start_date']) }}" required>
            </div>
            <div>
                <label class="label" for="end_date">End date</label>
                <input id="end_date" type="date" class="input" name="end_date" value="{{ old('end_date', $suggested['end_date']) }}" required>
            </div>
        </div>
        <div>
            <label class="label" for="payroll_date">Payroll date (optional)</label>
            <input id="payroll_date" type="date" class="input" name="payroll_date" value="{{ old('payroll_date') }}">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Create period</button>
            <a href="{{ route('admin.payroll.periods.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
