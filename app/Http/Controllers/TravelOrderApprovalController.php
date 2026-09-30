<?php

namespace App\Http\Controllers;

use App\Enums\LeaveDecision;
use App\Http\Requests\TravelOrder\DecideTravelOrderRequest;
use App\Models\TravelOrder;
use App\Services\TravelOrderPdfService;
use App\Services\TravelOrderService;
use Illuminate\Http\Request;

class TravelOrderApprovalController extends Controller
{
    public function __construct(
        private readonly TravelOrderService $travelOrders,
        private readonly TravelOrderPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TravelOrder::class);

        $orders = $this->travelOrders->pendingFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('travel-order.approvals.index', [
            'orders' => $orders,
            'mode' => 'pending',
        ]);
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', TravelOrder::class);

        $orders = $this->travelOrders->historyFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('travel-order.approvals.index', [
            'orders' => $orders,
            'mode' => 'history',
        ]);
    }

    public function show(Request $request, TravelOrder $travelOrder)
    {
        $this->authorize('view', $travelOrder);
        $travelOrder->load([
            'requester.department',
            'personnel.employee.department',
            'destinations',
            'assignments.user.employee',
            'actions.user',
        ]);

        return view('travel-order.approvals.show', [
            'order' => $travelOrder,
            'canEndorse' => $request->user()->can('endorse', $travelOrder),
            'canDownload' => $request->user()->can('download', $travelOrder),
        ]);
    }

    public function decide(DecideTravelOrderRequest $request, TravelOrder $travelOrder)
    {
        $this->travelOrders->decide(
            $travelOrder,
            $request->user(),
            LeaveDecision::from($request->validated('decision')),
            (string) ($request->validated('reason') ?? ''),
            $request->validated('signature')
        );

        return back()->with('success', 'Your decision was recorded.');
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
