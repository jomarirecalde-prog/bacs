<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewLeaveBalanceAdjustmentRequest;
use App\Http\Requests\Admin\StoreLeaveBalanceAdjustmentRequest;
use App\Http\Requests\Admin\UpdateLeaveEntitlementsRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveTypeRecord;
use App\Services\AuditLogger;
use App\Services\LeaveBalanceService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class LeaveEntitlementController extends Controller
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', \App\Models\LeaveBalance::class);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);

        $employees = Employee::query()
            ->with(['department', 'user', 'leaveBalances' => fn ($q) => $q->where('year', $year)])
            ->search($request->string('q')->toString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('employment_status'), fn ($q) => $q->where('employment_status', $request->string('employment_status')))
            ->when($request->filled('status'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('status', $request->string('status'))))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $rows = $employees->getCollection()->map(function (Employee $employee) use ($year) {
            $byCode = $employee->leaveBalances->keyBy('leave_type_code');
            $snapshot = [];

            foreach (['vacation', 'sick', 'birthday', 'bereavement'] as $code) {
                $balance = $byCode->get($code);
                $snapshot[$code] = $balance ? $this->balances->rowFromBalance($balance) : null;
            }

            return [
                'employee' => $employee,
                'balances' => $snapshot,
                'last_updated' => $employee->leaveBalances->max('updated_at'),
            ];
        });
        $employees->setCollection($rows);

        return view('admin.leave.entitlements.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(),
            'filters' => $request->only(['q', 'department_id', 'employment_status', 'status', 'year']),
            'year' => $year,
            'displayTypes' => ['vacation', 'sick', 'birthday', 'bereavement'],
        ]);
    }

    public function show(Employee $employee, Request $request)
    {
        $this->authorize('view', $employee);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);
        $employee->load('department');

        return view('admin.leave.entitlements.show', [
            'employee' => $employee,
            'year' => $year,
            'balances' => $this->balances->snapshot($employee, $year),
            'types' => LeaveTypeRecord::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Employee $employee, Request $request)
    {
        $this->authorize('manage', \App\Models\LeaveBalance::class);
        $this->authorize('view', $employee);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);
        $employee->load('department');

        return view('admin.leave.entitlements.edit', [
            'employee' => $employee,
            'year' => $year,
            'balances' => $this->balances->snapshot($employee, $year),
            'types' => LeaveTypeRecord::query()->active()->where('code', '!=', 'special')->orderBy('sort_order')->get(),
        ]);
    }

    public function previewAdjustment(PreviewLeaveBalanceAdjustmentRequest $request, Employee $employee)
    {
        $this->authorize('adjust', \App\Models\LeaveBalance::class);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);
        $data = $request->validated();
        $preview = $this->balances->previewManualAdjustment(
            $employee,
            $data['leave_type_code'],
            $data['adjustment_kind'],
            (float) $data['days'],
            $year,
        );

        if ($request->expectsJson()) {
            return response()->json($preview);
        }

        return back()->withInput()->with('adjustment_preview', $preview);
    }

    public function storeAdjustment(StoreLeaveBalanceAdjustmentRequest $request, Employee $employee)
    {
        $this->authorize('adjust', \App\Models\LeaveBalance::class);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);
        $data = $request->validated();

        $adjustment = $this->balances->applyManualAdjustment(
            employee: $employee,
            actor: $request->user(),
            code: $data['leave_type_code'],
            adjustmentKind: $data['adjustment_kind'],
            days: (float) $data['days'],
            reason: $data['reason'],
            effectiveDate: $data['effective_date'],
            authorizedByName: $data['authorized_by_name'] ?? $request->user()->name,
            year: $year,
        );

        $this->audit->log(
            $request->user(),
            'leave_balance_adjusted',
            'Leave',
            $adjustment->id,
            "Adjusted {$employee->fullName()} {$adjustment->leaveTypeLabel()} balance ({$adjustment->action_type->label()})."
        );

        return redirect()
            ->route('admin.leave.entitlements.edit', ['employee' => $employee, 'year' => $year])
            ->with('success', 'Leave balance adjustment recorded.');
    }

    public function adjustmentHistory(Employee $employee, Request $request)
    {
        $this->authorize('viewLeaveAdjustments', $employee);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);
        $employee->load('department');

        $adjustments = LeaveBalanceAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->when($request->filled('leave_type_code'), fn ($q) => $q->where('leave_type_code', $request->string('leave_type_code')))
            ->with(['updatedBy', 'leaveApplication'])
            ->latest('recorded_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.leave.entitlements.adjustments', [
            'employee' => $employee,
            'year' => $year,
            'adjustments' => $adjustments,
            'types' => LeaveTypeRecord::query()->active()->orderBy('sort_order')->get(),
            'filters' => $request->only(['leave_type_code', 'year']),
        ]);
    }

    public function leaveHistory(Employee $employee, Request $request)
    {
        $this->authorize('viewLeaveHistory', $employee);

        $employee->load('department');

        $applications = LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type', $request->string('leave_type')))
            ->with(['department'])
            ->latest('date_filed')
            ->paginate(20)
            ->withQueryString();

        return view('admin.leave.entitlements.leave-history', [
            'employee' => $employee,
            'applications' => $applications,
            'filters' => $request->only(['status', 'leave_type']),
        ]);
    }
}
