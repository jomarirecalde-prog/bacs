<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TravelOrderStatus;
use App\Enums\TravelTransportation;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TravelOrder;
use App\Services\TravelOrderPdfService;
use App\Services\TravelOrderService;
use Illuminate\Http\Request;

class TravelOrderController extends Controller
{
    public function __construct(
        private readonly TravelOrderService $travelOrders,
        private readonly TravelOrderPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAll', TravelOrder::class);

        $statusTab = $request->string('status', 'all')->toString();
        $query = TravelOrder::query()
            ->with(['requester.department', 'personnel.employee'])
            ->search($request->query('q'));

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('travel_date')) {
            $date = $request->date('travel_date');
            $query->whereDate('date_start', '<=', $date)->whereDate('date_end', '>=', $date);
        }

        if ($request->filled('approved_from')) {
            $query->whereDate('approved_at', '>=', $request->date('approved_from'));
        }

        if ($request->filled('approved_to')) {
            $query->whereDate('approved_at', '<=', $request->date('approved_to'));
        }

        if ($statusTab !== 'all') {
            $query->where('status', match ($statusTab) {
                'pending_endorsement' => TravelOrderStatus::PendingSupervisor,
                'pending_approval' => TravelOrderStatus::PendingCeoFinalApproval,
                'approved' => TravelOrderStatus::Approved,
                'cancelled' => TravelOrderStatus::Cancelled,
                'rejected' => TravelOrderStatus::Denied,
                default => $statusTab,
            });
        }

        $orders = $query->latest('submitted_at')->paginate(20)->withQueryString();
        $counts = $this->travelOrders->dashboardCounts();

        return view('admin.travel-orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statusTab' => $statusTab,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('view', $travelOrder);
        $travelOrder->load([
            'requester.department',
            'personnel.employee.department',
            'destinations',
            'attachments',
            'assignments.user.employee',
            'actions.user',
            'modificationLogs.modifier',
        ]);

        return view('admin.travel-orders.show', [
            'order' => $travelOrder,
            'canAdminEdit' => $request->user()->can('adminEditApproved', $travelOrder),
            'canAdminCancel' => $request->user()->can('adminCancelApproved', $travelOrder),
            'canDownload' => $request->user()->can('download', $travelOrder),
        ]);
    }

    public function edit(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('adminEditApproved', $travelOrder);

        return view('admin.travel-orders.edit', [
            'order' => $travelOrder->load(['personnel.employee.department', 'destinations', 'attachments']),
            'employee' => $travelOrder->requester,
            'transportOptions' => TravelTransportation::cases(),
            'searchUrl' => route('employee.travel-orders.employees.search'),
            'selectedTravelers' => $travelOrder->personnel->map(fn ($p) => [
                'id' => $p->employee_id,
                'name' => $p->employee?->fullName(),
                'position' => $p->employee?->position,
                'department' => $p->employee?->department?->name,
            ])->values(),
        ]);
    }

    public function updateApproved(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('adminEditApproved', $travelOrder);

        $validated = $request->validate([
            'edit_reason' => ['required', 'string', 'max:2000'],
            'official_station' => ['nullable', 'string', 'max:255'],
            'number_of_bh' => ['nullable', 'string', 'max:32'],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['string', 'max:500'],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'purpose' => ['required', 'string', 'max:5000'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'transportation' => ['nullable', 'string'],
            'transportation_other' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:32'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'traveler_ids' => ['required', 'array', 'min:1'],
            'traveler_ids.*' => ['integer', 'exists:employees,id'],
            'include_requester_as_traveler' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $validated['include_requester_as_traveler'] = $request->boolean('include_requester_as_traveler');

        $this->travelOrders->superAdminUpdateApproved(
            $travelOrder,
            $request->user(),
            $validated,
            $validated['edit_reason']
        );

        return redirect()
            ->route('admin.travel-orders.show', $travelOrder)
            ->with('success', 'Travel order updated.');
    }

    public function cancelApproved(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('adminCancelApproved', $travelOrder);
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $this->travelOrders->superAdminCancelApproved(
            $travelOrder,
            $request->user(),
            (string) $request->input('reason')
        );

        return redirect()
            ->route('admin.travel-orders.show', $travelOrder)
            ->with('success', 'Travel order cancelled.');
    }

    public function pdf(TravelOrder $travelOrder)
    {
        $this->authorize('download', $travelOrder);

        return $this->pdf->download($travelOrder);
    }

    public function print(TravelOrder $travelOrder)
    {
        $this->authorize('download', $travelOrder);

        return view('travel-order.print', $this->pdf->viewData($travelOrder));
    }
}
