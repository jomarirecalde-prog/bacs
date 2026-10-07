<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\OvertimeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class OvertimeRequestController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, PayrollPeriod $period)
    {
        $this->authorize('view', $period);

        $requests = OvertimeRequest::query()
            ->whereDate('attendance_date', '>=', $period->start_date)
            ->whereDate('attendance_date', '<=', $period->end_date)
            ->with(['employee:id,employee_number,full_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('attendance_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payroll.overtime.index', compact('period', 'requests'));
    }

    public function decide(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,deny'],
            'approved_minutes' => ['nullable', 'integer', 'min:0'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['decision'] === 'approve') {
            $minutes = $data['approved_minutes'] ?? $overtimeRequest->recorded_minutes;
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::Approved,
                'approved_minutes' => min($minutes, $overtimeRequest->recorded_minutes),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);
        } else {
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::Denied,
                'approved_minutes' => 0,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);
        }

        $this->audit->log($request->user(), 'overtime_decided', 'Payroll', $overtimeRequest->id, "OT {$data['decision']} for {$overtimeRequest->employee_id} on {$overtimeRequest->attendance_date->toDateString()}.");

        return back()->with('success', 'Overtime request updated.');
    }
}
