<?php

namespace App\Http\Controllers\Employee;

use App\Enums\OfficialBusinessStatus;
use App\Enums\TravelTransportation;
use App\Http\Controllers\Controller;
use App\Http\Requests\OfficialBusiness\StoreOfficialBusinessRequest;
use App\Models\OfficialBusinessRequest;
use App\Services\OfficialBusinessPdfService;
use App\Services\OfficialBusinessService;
use Illuminate\Http\Request;

class OfficialBusinessController extends Controller
{
    public function __construct(
        private readonly OfficialBusinessService $officialBusiness,
        private readonly OfficialBusinessPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OfficialBusinessRequest::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $this->officialBusiness->refreshStuckApprovalQueues();

        $tab = $request->string('tab', 'mine')->toString();
        $query = match ($tab) {
            'endorsement' => $this->officialBusiness->pendingFor($request->user())->select('official_business_requests.*'),
            'approval' => $this->officialBusiness->pendingFor($request->user())
                ->select('official_business_requests.*')
                ->where('status', OfficialBusinessStatus::PendingCeoFinalApproval),
            'participation' => OfficialBusinessRequest::query()->participating($employee),
            default => OfficialBusinessRequest::query()->ownedByEmployee($employee),
        };

        $requests = $query
            ->with(['employee.department', 'participants.employee'])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $counts = $this->officialBusiness->dashboardCounts($employee);

        return view('employee.official-business.index', compact('requests', 'tab', 'counts'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', OfficialBusinessRequest::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return view('employee.official-business.create', [
            'employee' => $employee,
            'transportOptions' => TravelTransportation::cases(),
            'searchUrl' => route('employee.official-business.employees.search'),
        ]);
    }

    public function store(StoreOfficialBusinessRequest $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();
        $data['include_requester_as_participant'] = $request->boolean('include_requester_as_participant');

        if ($data['action'] === 'draft') {
            $ob = $this->officialBusiness->saveDraft($employee, $request->user(), $data);

            return redirect()
                ->route('employee.official-business.show', $ob)
                ->with('success', 'Official business draft saved.');
        }

        $ob = $this->officialBusiness->submit($employee, $request->user(), $data);
        $this->officialBusiness->afterSubmit($ob);

        return redirect()
            ->route('employee.official-business.show', $ob)
            ->with('success', 'Official business request submitted for endorsement.');
    }

    public function edit(Request $request, OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('update', $officialBusinessRequest);
        $employee = $request->user()->employee;

        return view('employee.official-business.edit', [
            'employee' => $employee,
            'obRequest' => $officialBusinessRequest->load(['participants.employee.department']),
            'transportOptions' => TravelTransportation::cases(),
            'searchUrl' => route('employee.official-business.employees.search'),
            'selectedParticipants' => $officialBusinessRequest->participants
                ->where('participant_type', 'employee')
                ->whereNotNull('employee_id')
                ->map(fn ($p) => [
                    'id' => $p->employee_id,
                    'name' => $p->employee?->fullName() ?? $p->participant_name,
                    'position' => $p->employee?->position,
                    'department' => $p->employee?->department?->name,
                ])->values(),
            'externalParticipants' => $officialBusinessRequest->participants
                ->where('participant_type', 'external')
                ->pluck('participant_name')
                ->values()
                ->all(),
        ]);
    }

    public function update(StoreOfficialBusinessRequest $request, OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('update', $officialBusinessRequest);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();
        $data['include_requester_as_participant'] = $request->boolean('include_requester_as_participant');

        if ($data['action'] === 'draft') {
            $ob = $this->officialBusiness->saveDraft($employee, $request->user(), $data, $officialBusinessRequest);

            return redirect()->route('employee.official-business.show', $ob)->with('success', 'Draft updated.');
        }

        $ob = $this->officialBusiness->submit($employee, $request->user(), $data, $officialBusinessRequest);
        $this->officialBusiness->afterSubmit($ob);

        return redirect()->route('employee.official-business.show', $ob)->with('success', 'Official business request submitted.');
    }

    public function show(Request $request, OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('view', $officialBusinessRequest);
        $officialBusinessRequest->load([
            'employee.department',
            'participants.employee.department',
            'assignments.user.employee',
            'actions.user',
        ]);

        return view('employee.official-business.show', [
            'obRequest' => $officialBusinessRequest,
            'canEdit' => $request->user()->can('update', $officialBusinessRequest),
            'canCancel' => $request->user()->can('cancel', $officialBusinessRequest),
            'canEndorse' => $request->user()->can('endorse', $officialBusinessRequest),
            'canDownload' => $request->user()->can('download', $officialBusinessRequest),
        ]);
    }

    public function cancel(Request $request, OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('cancel', $officialBusinessRequest);
        $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->officialBusiness->cancel($officialBusinessRequest, $request->user(), (string) $request->input('reason', ''));

        return back()->with('success', 'Official business request cancelled.');
    }

    public function searchEmployees(Request $request)
    {
        $this->authorize('create', OfficialBusinessRequest::class);

        return response()->json([
            'results' => $this->officialBusiness->searchEmployees((string) $request->query('q', '')),
        ]);
    }

    public function pdf(OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('download', $officialBusinessRequest);

        return $this->pdf->download($officialBusinessRequest);
    }

    public function preview(OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('download', $officialBusinessRequest);

        return $this->pdf->stream($officialBusinessRequest);
    }

    public function print(OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('download', $officialBusinessRequest);

        return view('official-business.print', $this->pdf->viewData($officialBusinessRequest));
    }
}
