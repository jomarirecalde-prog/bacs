<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Services\DirectoryCatalog;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class LeaveReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAll', LeaveApplication::class);

        $query = LeaveApplication::query()
            ->with(['employee.department', 'department'])
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type', $request->query('leave_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('end_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->query('to')));

        $applications = (clone $query)->latest('date_filed')->paginate(30)->withQueryString();

        $counts = [
            'total' => (clone $query)->count(),
            'approved' => (clone $query)->where('status', LeaveStatus::Approved)->count(),
            'denied' => (clone $query)->where('status', LeaveStatus::Denied)->count(),
            'pending' => (clone $query)->whereIn('status', [
                LeaveStatus::PendingSupervisor,
                LeaveStatus::PendingDepartmentHead,
                LeaveStatus::PendingAdministrativeHead,
                LeaveStatus::PendingHr,
                LeaveStatus::PartiallyApproved,
            ])->count(),
            'days' => (clone $query)->where('status', LeaveStatus::Approved)->sum('requested_days'),
        ];

        return view('admin.leave.reports', [
            'applications' => $applications,
            'counts' => $counts,
            'departments' => app(DirectoryCatalog::class)->departments(),
            'types' => LeaveType::cases(),
            'statuses' => LeaveStatus::cases(),
            'filters' => $request->only(['department_id', 'leave_type', 'status', 'from', 'to']),
            'generated' => ManilaTime::formatDateTime(ManilaTime::now()),
        ]);
    }
}
