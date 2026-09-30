<?php

namespace App\Http\Controllers\Employee;

use App\Enums\TravelOrderStatus;
use App\Enums\TravelTransportation;
use App\Http\Controllers\Controller;
use App\Http\Requests\TravelOrder\StoreTravelOrderRequest;
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
        $this->authorize('viewAny', TravelOrder::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $tab = $request->string('tab', 'mine')->toString();
        $query = match ($tab) {
            'participation' => TravelOrder::query()->participating($employee),
            'endorsement' => $this->travelOrders->pendingFor($request->user())->select('travel_orders.*'),
            'approval' => TravelOrder::query()
                ->whereHas('assignments', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'pending'))
                ->where('status', TravelOrderStatus::PendingCeoFinalApproval),
            default => TravelOrder::query()->ownedByRequester($employee),
        };

        $orders = $query
            ->with(['requester.department', 'personnel.employee'])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $counts = $this->travelOrders->dashboardCounts($employee);

        return view('employee.travel-orders.index', compact('orders', 'tab', 'counts'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', TravelOrder::class);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return view('employee.travel-orders.create', [
            'employee' => $employee,
            'transportOptions' => TravelTransportation::cases(),
            'searchUrl' => route('employee.travel-orders.employees.search'),
        ]);
    }

    public function store(StoreTravelOrderRequest $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();
        $data['include_requester_as_traveler'] = $request->boolean('include_requester_as_traveler');

        if ($data['action'] === 'draft') {
            $order = $this->travelOrders->saveDraft($employee, $request->user(), $data);

            return redirect()
                ->route('employee.travel-orders.show', $order)
                ->with('success', 'Travel order draft saved.');
        }

        $order = $this->travelOrders->submit($employee, $request->user(), $data);
        $this->travelOrders->afterSubmit($order);

        return redirect()
            ->route('employee.travel-orders.show', $order)
            ->with('success', 'Travel order submitted for endorsement.');
    }

    public function edit(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('update', $travelOrder);
        $employee = $request->user()->employee;

        return view('employee.travel-orders.edit', [
            'employee' => $employee,
            'order' => $travelOrder->load(['personnel.employee.department', 'destinations', 'attachments']),
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

    public function update(StoreTravelOrderRequest $request, TravelOrder $travelOrder)
    {
        $this->authorize('update', $travelOrder);
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validated();
        $data['include_requester_as_traveler'] = $request->boolean('include_requester_as_traveler');

        if ($data['action'] === 'draft') {
            $order = $this->travelOrders->saveDraft($employee, $request->user(), $data, $travelOrder);

            return redirect()->route('employee.travel-orders.show', $order)->with('success', 'Draft updated.');
        }

        $order = $this->travelOrders->submit($employee, $request->user(), $data, $travelOrder);
        $this->travelOrders->afterSubmit($order);

        return redirect()->route('employee.travel-orders.show', $order)->with('success', 'Travel order submitted.');
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
        ]);

        return view('employee.travel-orders.show', [
            'order' => $travelOrder,
            'canEdit' => $request->user()->can('update', $travelOrder),
            'canCancel' => $request->user()->can('cancel', $travelOrder),
            'canEndorse' => $request->user()->can('endorse', $travelOrder),
            'canDownload' => $request->user()->can('download', $travelOrder),
        ]);
    }

    public function cancel(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('cancel', $travelOrder);
        $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->travelOrders->cancel($travelOrder, $request->user(), (string) $request->input('reason', ''));

        return back()->with('success', 'Travel order cancelled.');
    }

    public function searchEmployees(Request $request)
    {
        $this->authorize('create', TravelOrder::class);

        return response()->json([
            'results' => $this->travelOrders->searchEmployees((string) $request->query('q', '')),
        ]);
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
