@extends('layouts.app')

@section('title', 'Review '.$application->application_number)
@section('page-title', $application->application_number)
@section('page-subtitle', $application->employee?->fullName().' · '.$application->leaveTypeLabel())

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('leave.approvals.index') }}" class="btn-outline btn-sm">Back to pending requests</a>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary btn-sm" href="{{ route('leave.approvals.pdf', $application) }}">Download PDF</a>
            <a class="btn-outline btn-sm" href="{{ route('leave.approvals.print', $application) }}" target="_blank">Print official form</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 overflow-auto rounded-2xl border border-line bg-white p-3 shadow-soft">
            @include('leave.partials.official-form-css')
            @include('leave.partials.official-form')
        </div>
        <div class="space-y-4">
            <div class="card card-accent-gold">
                <div class="card-header"><h3 class="card-title">Approval progress</h3></div>
                <div class="card-body">@include('leave.partials.timeline')</div>
            </div>

            @if ($canApprove)
                <div class="card card-accent-brand" x-data="{ decision: 'approved' }">
                    <div class="card-header"><h3 class="card-title">Your decision</h3></div>
                    <form method="POST" action="{{ route('leave.approvals.decide', $application) }}" class="card-body space-y-4" @submit="beforeSubmit">
                        @csrf
                        <label class="flex items-center gap-2">
                            <input type="radio" name="decision" value="approved" class="radio" x-model="decision">
                            <span class="font-semibold text-brand-800">APPROVED</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="decision" value="denied" class="radio" x-model="decision">
                            <span class="font-semibold text-critical-700">DENIED</span>
                        </label>
                        <div>
                            <label class="label" for="reason">Reason <span x-show="decision === 'denied'" class="text-critical-600">*</span></label>
                            <textarea id="reason" name="reason" rows="3" class="textarea" :required="decision === 'denied'"></textarea>
                            @error('reason') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div x-data="signaturePad()">
                            <div class="label">Name &amp; signature</div>
                            <canvas x-ref="canvas" class="h-28 w-full cursor-crosshair rounded-xl border border-line bg-white" width="480" height="120"></canvas>
                            <input type="hidden" name="signature" x-ref="input">
                            <button type="button" class="btn-ghost btn-sm mt-1" @click="clear()">Clear</button>
                        </div>
                        <button class="btn-primary btn-block" type="submit">Record decision</button>
                    </form>
                </div>
            @endif

            @if ($canProcessHr)
                @include('leave.partials.hr-form')
            @endif
        </div>
    </div>
</div>
@endsection
