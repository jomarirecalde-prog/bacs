@php
    $activitiesJson = old('activities', isset($report)
        ? $report->activities->map(fn ($row) => [
            'activity_description' => $row->activity_description,
            'activity_date' => $row->activity_date?->toDateString(),
            'time_from' => substr((string) $row->time_from, 0, 5),
            'time_to' => substr((string) $row->time_to, 0, 5),
            'status_remark' => $row->status_remark,
            'reviewed_vouched_by' => $row->reviewed_vouched_by,
        ])->values()->all()
        : [['activity_description' => '', 'activity_date' => '', 'time_from' => '', 'time_to' => '', 'status_remark' => '', 'reviewed_vouched_by' => '']]);
@endphp

<div
    class="space-y-5"
    x-data="{
        activities: @js($activitiesJson),
        addRow() {
            this.activities.push({ activity_description: '', activity_date: '', time_from: '', time_to: '', status_remark: '', reviewed_vouched_by: '' });
        },
        removeRow(index) {
            if (this.activities.length > 1) this.activities.splice(index, 1);
        },
        coverageEndSameDay() {
            const start = this.$refs.coverageStart;
            const end = this.$refs.coverageEnd;
            if (! start?.value) {
                return;
            }
            end.value = start.value;
            end.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }"
>
    @php
        $linkedOt = isset($report)
            ? $report->overtimeRequest
            : ($linkedOvertimeRequest ?? null);
        $linkedOtDate = $linkedOt?->attendance_date;
        $linkedOtRecorded = $linkedOt ? sprintf('%d:%02d', intdiv($linkedOt->recorded_minutes, 60), $linkedOt->recorded_minutes % 60) : null;
    @endphp
    @unless(isset($report))
        <input type="hidden" name="overtime_request_id" value="{{ old('overtime_request_id', $preselectedOvertimeRequestId ?? '') }}">
        @error('overtime_request_id')<p class="error-text">{{ $message }}</p>@enderror
    @endunless
    @if ($linkedOt)
        <div class="alert-info text-sm">
            Linked payroll OT: <strong>{{ $linkedOtDate?->format('M j, Y') }}</strong>
            @if ($linkedOtRecorded)
                · DTR recorded <span class="font-semibold tabular-nums">{{ $linkedOtRecorded }}</span>
            @endif
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="date_coverage_start">Coverage start</label>
            <input type="date" id="date_coverage_start" name="date_coverage_start" x-ref="coverageStart" class="input @error('date_coverage_start') input-error @enderror"
                value="{{ old('date_coverage_start', isset($report) ? $report->date_coverage_start?->toDateString() : ($linkedOtDate?->toDateString() ?? '')) }}">
            @error('date_coverage_start')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="date_coverage_end">Coverage end</label>
            <div class="flex gap-2">
                <input type="date" id="date_coverage_end" name="date_coverage_end" x-ref="coverageEnd" class="input min-w-0 flex-1 @error('date_coverage_end') input-error @enderror"
                    value="{{ old('date_coverage_end', isset($report) ? $report->date_coverage_end?->toDateString() : ($linkedOtDate?->toDateString() ?? '')) }}">
                <button type="button" class="btn-outline btn-sm shrink-0 whitespace-nowrap" @click="coverageEndSameDay()">Same day</button>
            </div>
            @error('date_coverage_end')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="date_submitted">Date submitted (form)</label>
            <input type="date" id="date_submitted" name="date_submitted" class="input"
                value="{{ old('date_submitted', isset($report) ? $report->date_submitted?->toDateString() : now()->toDateString()) }}">
        </div>
        <div>
            <label class="label" for="prepared_by">Prepared by (printed name)</label>
            <input type="text" id="prepared_by" name="prepared_by" class="input @error('prepared_by') input-error @enderror"
                value="{{ old('prepared_by', isset($report) ? ($report->prepared_by ?? $employee->fullName()) : $employee->fullName()) }}">
            @error('prepared_by')<p class="error-text">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="label" for="approved_by">Approved by (printed name, optional)</label>
            <input type="text" id="approved_by" name="approved_by" class="input" value="{{ old('approved_by', isset($report) ? ($report->approved_by ?? '') : '') }}">
        </div>
    </div>

    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-ink">Activities</h3>
            <button type="button" class="btn-outline btn-sm" @click="addRow()">Add row</button>
        </div>
        @error('activities')<p class="error-text mb-2">{{ $message }}</p>@enderror
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">No.</th>
                        <th>Activities</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status / Remark</th>
                        <th>Reviewed / Vouched</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in activities" :key="index">
                        <tr>
                            <td class="tabular-nums" x-text="index + 1"></td>
                            <td>
                                <input type="hidden" :name="`activities[${index}][activity_description]`" :value="row.activity_description">
                                <input type="text" class="input input-sm w-full min-w-[10rem]" x-model="row.activity_description" placeholder="Activity">
                            </td>
                            <td>
                                <input type="hidden" :name="`activities[${index}][activity_date]`" :value="row.activity_date">
                                <input type="date" class="input input-sm" x-model="row.activity_date">
                            </td>
                            <td>
                                <input type="hidden" :name="`activities[${index}][time_from]`" :value="row.time_from">
                                <input type="time" class="input input-sm" x-model="row.time_from">
                            </td>
                            <td>
                                <input type="hidden" :name="`activities[${index}][time_to]`" :value="row.time_to">
                                <input type="time" class="input input-sm" x-model="row.time_to">
                            </td>
                            <td>
                                <input type="hidden" :name="`activities[${index}][status_remark]`" :value="row.status_remark">
                                <input type="text" class="input input-sm w-full" x-model="row.status_remark">
                            </td>
                            <td>
                                <input type="hidden" :name="`activities[${index}][reviewed_vouched_by]`" :value="row.reviewed_vouched_by">
                                <input type="text" class="input input-sm w-full" x-model="row.reviewed_vouched_by">
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn-ghost btn-sm text-critical" @click="removeRow(index)" x-show="activities.length > 1">Remove</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-t border-line pt-4">
        <button type="submit" name="action" value="draft" class="btn-outline">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary">Submit for approval</button>
    </div>
</div>
