@extends('layouts.app')

@section('title', $application->application_number)
@section('page-title', $application->application_number)
@section('page-subtitle', $application->leaveTypeLabel().' · '.$application->status->label())

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('employee.leave.index') }}" class="btn-outline btn-sm">Back to my applications</a>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary btn-sm" href="{{ route('employee.leave.pdf', $application) }}">Download PDF</a>
            <a class="btn-outline btn-sm" href="{{ route('employee.leave.print', $application) }}" target="_blank">Print official form</a>
            @if ($canCancel)
                <form method="POST" action="{{ route('employee.leave.cancel', $application) }}" onsubmit="return confirm('Cancel this leave application?')">
                    @csrf
                    <button class="btn-outline-danger btn-sm" type="submit">Cancel application</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 space-y-4">
            <div class="overflow-auto rounded-2xl border border-line bg-white p-3 shadow-soft">
                @include('leave.partials.official-form-css')
                @include('leave.partials.official-form')
            </div>
        </div>
        <div class="space-y-4">
            <div class="card card-accent-gold">
                <div class="card-header"><h3 class="card-title">Approval progress</h3></div>
                <div class="card-body">
                    @include('leave.partials.timeline')
                </div>
            </div>
            @if ($application->payment_type)
                <div class="card">
                    <div class="card-body">
                        <div class="stat-label">Payment classification</div>
                        <div class="mt-1 font-bold text-ink">{{ $application->payment_type->label() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
