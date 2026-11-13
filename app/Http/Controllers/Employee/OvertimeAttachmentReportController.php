<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\OvertimeAttachment\StoreOvertimeAttachmentReportRequest;
use App\Models\OvertimeAttachmentReport;
use App\Models\OvertimeRequest;
use App\Services\OvertimeAttachmentReportPdfService;
use App\Services\OvertimeAttachmentReportService;
use App\Services\Payroll\OvertimeRequestSyncService;
use Illuminate\Http\Request;

class OvertimeAttachmentReportController extends Controller
{
    public function __construct(
        private readonly OvertimeAttachmentReportService $reports,
        private readonly OvertimeAttachmentReportPdfService $pdf,
        private readonly OvertimeRequestSyncService $overtimeSync,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OvertimeAttachmentReport::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $this->overtimeSync->syncForEmployee($employee);

        $reports = OvertimeAttachmentReport::query()
            ->ownedByEmployee($employee)
            ->with(['overtimeRequest', 'officialTimeRequest', 'activities'])
            ->latest('updated_at')
            ->get()
            ->each(fn (OvertimeAttachmentReport $report) => $this->reports->alignWithApprovedPayrollOvertime($report))
            ->values();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 15;
        $reports = new \Illuminate\Pagination\LengthAwarePaginator(
            $reports->forPage($page, $perPage)->values(),
            $reports->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $eligibleOvertime = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->where('recorded_minutes', '>', 0)
            ->whereDoesntHave('attachmentReport')
            ->orderByDesc('attendance_date')
            ->limit(20)
            ->get();

        return view('employee.overtime-attachment.index', [
            'reports' => $reports,
            'counts' => $this->reports->dashboardCounts($employee),
            'eligibleOvertime' => $eligibleOvertime,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', OvertimeAttachmentReport::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $employee->load(['department', 'designation']);

        $this->overtimeSync->syncForEmployee($employee);

        $preselected = $request->integer('overtime_request_id') ?: null;

        if (! $preselected) {
            return redirect()
                ->route('employee.overtime-attachment.index')
                ->with('warning', 'Choose a DTR overtime row from the list below to attach a report.');
        }

        $linkedOvertimeRequest = OvertimeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereKey($preselected)
            ->where('recorded_minutes', '>', 0)
            ->whereDoesntHave('attachmentReport')
            ->first();

        if (! $linkedOvertimeRequest) {
            return redirect()
                ->route('employee.overtime-attachment.index')
                ->with('error', 'That overtime row is not available for a new attachment report.');
        }

        return view('employee.overtime-attachment.create', [
            'employee' => $employee,
            'preselectedOvertimeRequestId' => $preselected,
            'linkedOvertimeRequest' => $linkedOvertimeRequest,
        ]);
    }

    public function store(StoreOvertimeAttachmentReportRequest $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();

        if ($data['action'] === 'draft') {
            $report = $this->reports->saveDraft($employee, $request->user(), $data);

            return redirect()->route('employee.overtime-attachment.show', $report)->with('success', 'Draft saved.');
        }

        $report = $this->reports->submit($employee, $request->user(), $data);

        return redirect()->route('employee.overtime-attachment.show', $report)->with('success', 'OT Attachment report submitted for payroll review.');
    }

    public function show(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('view', $overtimeAttachmentReport);
        $overtimeAttachmentReport = $this->reports->alignWithApprovedPayrollOvertime($overtimeAttachmentReport);
        $overtimeAttachmentReport->load([
            'employee.department',
            'overtimeRequest',
            'officialTimeRequest',
            'activities',
            'actions.user',
        ]);

        return view('employee.overtime-attachment.show', [
            'report' => $overtimeAttachmentReport,
            'canEdit' => $request->user()->can('update', $overtimeAttachmentReport),
            'canCancel' => $request->user()->can('cancel', $overtimeAttachmentReport),
            'canPdf' => $request->user()->can('downloadPdf', $overtimeAttachmentReport),
        ]);
    }

    public function edit(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('update', $overtimeAttachmentReport);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $employee->load(['department', 'designation']);

        $overtimeAttachmentReport->load(['activities', 'overtimeRequest']);

        return view('employee.overtime-attachment.edit', [
            'employee' => $employee,
            'report' => $overtimeAttachmentReport,
        ]);
    }

    public function update(StoreOvertimeAttachmentReportRequest $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();

        if ($data['action'] === 'draft') {
            $report = $this->reports->saveDraft($employee, $request->user(), $data, $overtimeAttachmentReport);

            return redirect()->route('employee.overtime-attachment.show', $report)->with('success', 'Draft updated.');
        }

        $report = $this->reports->submit($employee, $request->user(), $data, $overtimeAttachmentReport);

        return redirect()->route('employee.overtime-attachment.show', $report)->with('success', 'OT Attachment report resubmitted.');
    }

    public function submit(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('update', $overtimeAttachmentReport);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validate([
            'prepared_by' => ['nullable', 'string', 'max:255'],
        ]);

        $overtimeAttachmentReport->load('activities');

        $report = $this->reports->submit($employee, $request->user(), [
            'action' => 'submit',
            'prepared_by' => $data['prepared_by'] ?? $overtimeAttachmentReport->prepared_by,
            'date_coverage_start' => $overtimeAttachmentReport->date_coverage_start?->toDateString(),
            'date_coverage_end' => $overtimeAttachmentReport->date_coverage_end?->toDateString(),
            'date_submitted' => $overtimeAttachmentReport->date_submitted?->toDateString(),
            'approved_by' => $overtimeAttachmentReport->approved_by,
            'activities' => $overtimeAttachmentReport->activities->map(fn ($row) => [
                'activity_description' => $row->activity_description,
                'activity_date' => $row->activity_date?->toDateString(),
                'time_from' => substr((string) $row->time_from, 0, 5),
                'time_to' => substr((string) $row->time_to, 0, 5),
                'status_remark' => $row->status_remark,
                'reviewed_vouched_by' => $row->reviewed_vouched_by,
            ])->all(),
        ], $overtimeAttachmentReport);

        return redirect()->route('employee.overtime-attachment.show', $report)->with('success', 'Report submitted for approval.');
    }

    public function cancel(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('cancel', $overtimeAttachmentReport);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->reports->cancel($overtimeAttachmentReport, $request->user(), (string) ($data['reason'] ?? ''));

        return redirect()->route('employee.overtime-attachment.index')->with('success', 'Report cancelled.');
    }

    public function pdf(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('downloadPdf', $overtimeAttachmentReport);

        return $this->pdf->download($overtimeAttachmentReport);
    }

    public function preview(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('downloadPdf', $overtimeAttachmentReport);

        return $this->pdf->stream($overtimeAttachmentReport);
    }
}
