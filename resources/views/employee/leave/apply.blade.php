@extends('layouts.app')

@section('title', 'Apply for Leave')
@section('page-title', 'Apply for Leave')
@section('page-subtitle', 'Official BACS Leave Application Form')

@section('content')
<div class="space-y-6" x-data="leaveApply({
    previewUrl: @js(route('employee.leave.preview-days')),
    name: @js($employee->fullName()),
    department: @js($employee->department?->name),
    employeeId: @js($employee->employee_number),
    position: @js($employee->position),
    start: @js(old('start_date', $today)),
    end: @js(old('end_date', $today)),
    type: @js(old('leave_type', 'vacation')),
    special: @js(old('special_leave_type')),
    reason: @js(old('reason')),
})">
    <form method="POST" action="{{ route('employee.leave.store') }}" @submit="beforeSubmit">
        @csrf
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="card card-accent-brand">
                    <div class="card-header">
                        <h2 class="card-title">Leave details</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="label">Name</div>
                                <div class="input bg-canvas">{{ $employee->fullName() }}</div>
                            </div>
                            <div>
                                <div class="label">Department</div>
                                <div class="input bg-canvas">{{ $employee->department?->name ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="label">Employee ID</div>
                                <div class="input bg-canvas">{{ $employee->employee_number }}</div>
                            </div>
                            <div>
                                <div class="label">Position</div>
                                <div class="input bg-canvas">{{ $employee->position ?? '—' }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="label">Particular leave <span class="text-critical-600">*</span></div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($types as $type)
                                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5"
                                           :class="type === @js($type->value) ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300' : 'border-line hover:border-brand-200'">
                                        <input type="radio" name="leave_type" value="{{ $type->value }}" class="radio mt-0.5" x-model="type" @change="refreshDays()">
                                        <span>
                                            <span class="text-sm font-semibold text-ink">{{ $type->label() }}</span>
                                            @if ($type !== \App\Enums\LeaveType::Special)
                                                <span class="mt-0.5 block text-[11px] text-muted">{{ $type->defaultDays() }} day entitlement</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('leave_type') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div x-show="type === 'special'" x-cloak>
                            <label class="label" for="special_leave_type">Special leave type <span class="text-critical-600">*</span></label>
                            <select id="special_leave_type" name="special_leave_type" class="select" x-model="special" @change="refreshDays()">
                                <option value="">Select type</option>
                                @foreach ($specialTypes as $special)
                                    <option value="{{ $special->value }}">{{ $special->label() }} ({{ $special->defaultDays() }} days)</option>
                                @endforeach
                            </select>
                            @error('special_leave_type') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="start_date">From <span class="text-critical-600">*</span></label>
                                <input id="start_date" type="date" name="start_date" class="input" x-model="start" @change="refreshDays()" required>
                                @error('start_date') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="end_date">To <span class="text-critical-600">*</span></label>
                                <input id="end_date" type="date" name="end_date" class="input" x-model="end" @change="refreshDays()" required>
                                @error('end_date') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm">
                            <span class="text-xs font-bold uppercase tracking-wide text-gold-800">No. of days requested</span>
                            <div class="mt-1 text-2xl font-extrabold text-ink tabular-nums" x-text="daysLabel">—</div>
                            <p class="hint">Workdays are counted for regular leave. Special leave uses calendar days. Rest days and holidays are excluded from vacation, sick, birthday, and bereavement counts.</p>
                        </div>

                        <div>
                            <label class="label" for="reason">Reason <span class="text-critical-600">*</span></label>
                            <textarea id="reason" name="reason" rows="4" class="textarea" x-model="reason" required maxlength="2000" placeholder="State the reason for this leave request"></textarea>
                            @error('reason') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="card card-accent-gold">
                    <div class="card-header">
                        <h2 class="card-title">Employee declaration</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <p class="text-sm text-ink-soft">I hereby request for my Service Incentive Leave in accordance with company policy. I certify that the information provided above is true and accurate to the best of my knowledge.</p>
                        <label class="flex items-start gap-2.5 text-sm">
                            <input type="checkbox" name="declaration_accepted" value="1" class="checkbox mt-0.5" required @checked(old('declaration_accepted'))>
                            <span>I certify this declaration and confirm my electronic signature below.</span>
                        </label>
                        @error('declaration_accepted') <p class="error-text">{{ $message }}</p> @enderror

                        <div>
                            <div class="label">Employee’s print name</div>
                            <div class="input bg-canvas font-semibold">{{ $employee->fullName() }}</div>
                        </div>

                        <div x-data="signaturePad()">
                            <div class="label">Electronic signature <span class="text-critical-600">*</span></div>
                            <canvas x-ref="canvas" class="h-36 w-full cursor-crosshair rounded-xl border border-line bg-white" width="640" height="160"></canvas>
                            <input type="hidden" name="employee_signature" x-ref="input" value="{{ old('employee_signature') }}">
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="btn-outline btn-sm" @click="clear()">Clear signature</button>
                            </div>
                            @error('employee_signature') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-primary">Review and submit application</button>
                    <a href="{{ route('employee.leave.index') }}" class="btn-outline">Cancel</a>
                </div>
            </div>

            <div class="space-y-4">
                <div class="card card-accent-info">
                    <div class="card-header"><h3 class="card-title">Leave balances</h3></div>
                    <dl class="divide-y divide-line px-5 text-sm">
                        @foreach (['vacation' => 'Vacation', 'sick' => 'Sick', 'birthday' => 'Birthday', 'bereavement' => 'Bereavement'] as $code => $label)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt class="text-muted">{{ $label }}</dt>
                                <dd class="font-bold tabular-nums">{{ $balances[$code]['remaining'] ?? 0 }} / {{ $balances[$code]['entitled'] ?? 0 }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <div class="alert-info">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-xs">After you submit, Immediate Supervisors/Superiors receive the request at the same time (parallel approval). Leave balance is deducted only after HR finalizes an approved application.</span>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
