<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OfficialTimeStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\OfficialTimeRequest;
use App\Models\OfficialTimeType;
use Illuminate\Http\Request;

class OfficialTimeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAll', OfficialTimeRequest::class);

        $query = OfficialTimeRequest::query()
            ->with(['employee.department', 'designation', 'officialTimeType'])
            ->latest('submitted_at');

        if ($request->filled('request_no')) {
            $query->where('request_no', 'like', '%'.$request->string('request_no').'%');
        }
        if ($request->filled('employee')) {
            $term = '%'.$request->string('employee').'%';
            $query->whereHas('employee', fn ($q) => $q->where('full_name', 'like', $term)->orWhere('employee_number', 'like', $term));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }
        if ($request->filled('designation_id')) {
            $query->where('designation_id', (int) $request->input('designation_id'));
        }
        if ($request->filled('official_time_type_id')) {
            $query->where('official_time_type_id', (int) $request->input('official_time_type_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->string('date_to'));
        }

        return view('admin.official-time.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('designation_name')->get(['id', 'designation_name']),
            'types' => OfficialTimeType::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => OfficialTimeStatus::cases(),
            'filters' => $request->only(['request_no', 'employee', 'department_id', 'designation_id', 'official_time_type_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function show(OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('view', $officialTimeRequest);
        $officialTimeRequest->load(['employee.department', 'designation', 'officialTimeType', 'assignments.user', 'actions.user']);

        return view('admin.official-time.show', [
            'ot' => $officialTimeRequest,
            'canDownload' => auth()->user()->can('downloadAttachment', $officialTimeRequest),
        ]);
    }
}
