<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OfficialBusinessStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
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
        $this->authorize('viewAll', OfficialBusinessRequest::class);

        $statusTab = $request->string('status', 'all')->toString();
        $query = OfficialBusinessRequest::query()
            ->with(['employee.department', 'participants.employee'])
            ->search($request->query('q'));

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('destination')) {
            $like = '%'.$request->string('destination').'%';
            $query->where(function ($q) use ($like) {
                $q->where('destination_from', 'like', $like)
                    ->orWhere('destination_to', 'like', $like);
            });
        }

        if ($request->filled('covered_date')) {
            $date = $request->date('covered_date');
            $query->whereDate('date_covered_start', '<=', $date)->whereDate('date_covered_end', '>=', $date);
        }

        if ($statusTab !== 'all') {
            $query->where('status', match ($statusTab) {
                'pending_endorsement' => OfficialBusinessStatus::PendingSupervisor,
                'pending_approval' => OfficialBusinessStatus::PendingCeoFinalApproval,
                'approved' => OfficialBusinessStatus::Approved,
                'cancelled' => OfficialBusinessStatus::Cancelled,
                'rejected' => OfficialBusinessStatus::Denied,
                default => $statusTab,
            });
        }

        $requests = $query->latest('submitted_at')->paginate(20)->withQueryString();
        $counts = $this->officialBusiness->dashboardCounts();

        return view('admin.official-business.index', [
            'requests' => $requests,
            'counts' => $counts,
            'statusTab' => $statusTab,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
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

        return view('admin.official-business.show', [
            'obRequest' => $officialBusinessRequest,
            'canDownload' => $request->user()->can('download', $officialBusinessRequest),
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
}
