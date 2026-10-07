<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfficialTime\StoreOfficialTimeRequest;
use App\Models\OfficialTimeRequest;
use App\Models\OfficialTimeType;
use App\Services\OfficialTimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OfficialTimeController extends Controller
{
    public function __construct(private readonly OfficialTimeService $officialTime) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OfficialTimeRequest::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $requests = OfficialTimeRequest::query()
            ->ownedByEmployee($employee)
            ->with(['officialTimeType', 'department', 'designation'])
            ->latest('updated_at')
            ->paginate(15);

        return view('employee.official-time.index', [
            'requests' => $requests,
            'counts' => $this->officialTime->dashboardCounts($employee),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', OfficialTimeRequest::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $employee->load(['department', 'designation']);

        return view('employee.official-time.create', [
            'employee' => $employee,
            'types' => OfficialTimeType::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreOfficialTimeRequest $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();

        if ($data['action'] === 'draft') {
            $ot = $this->officialTime->saveDraft($employee, $request->user(), $data);

            return redirect()->route('employee.official-time.show', $ot)->with('success', 'Draft saved.');
        }

        $ot = $this->officialTime->submit($employee, $request->user(), $data);
        $this->officialTime->afterSubmit($ot);

        return redirect()->route('employee.official-time.show', $ot)->with('success', 'Official Time submitted for endorsement.');
    }

    public function show(Request $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('view', $officialTimeRequest);
        $officialTimeRequest->load(['employee.department', 'designation', 'officialTimeType', 'assignments.user', 'actions.user']);

        return view('employee.official-time.show', [
            'ot' => $officialTimeRequest,
            'canEdit' => $request->user()->can('update', $officialTimeRequest),
            'canCancel' => $request->user()->can('cancel', $officialTimeRequest),
            'canDownload' => $request->user()->can('downloadAttachment', $officialTimeRequest),
        ]);
    }

    public function edit(Request $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('update', $officialTimeRequest);
        $request->user()->employee?->load(['department', 'designation']);

        return view('employee.official-time.edit', [
            'employee' => $request->user()->employee,
            'ot' => $officialTimeRequest->load('officialTimeType'),
            'types' => OfficialTimeType::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(StoreOfficialTimeRequest $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('update', $officialTimeRequest);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();

        if ($data['action'] === 'draft') {
            $ot = $this->officialTime->saveDraft($employee, $request->user(), $data, $officialTimeRequest);

            return redirect()->route('employee.official-time.show', $ot)->with('success', 'Draft updated.');
        }

        $ot = $this->officialTime->submit($employee, $request->user(), $data, $officialTimeRequest);
        $this->officialTime->afterSubmit($ot);

        return redirect()->route('employee.official-time.show', $ot)->with('success', 'Official Time resubmitted.');
    }

    public function cancel(Request $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('cancel', $officialTimeRequest);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->officialTime->cancel($officialTimeRequest, $request->user(), (string) ($data['reason'] ?? ''));

        return redirect()->route('employee.official-time.index')->with('success', 'Official Time request cancelled.');
    }

    public function attachment(OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('downloadAttachment', $officialTimeRequest);
        abort_unless($officialTimeRequest->attachment_path && Storage::disk('local')->exists($officialTimeRequest->attachment_path), 404);

        return Storage::disk('local')->download($officialTimeRequest->attachment_path, $officialTimeRequest->attachment_name ?: 'attachment');
    }
}
