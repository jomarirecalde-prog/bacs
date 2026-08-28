<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeaveEntitlementsRequest;
use App\Models\LeaveTypeRecord;
use App\Services\AuditLogger;

class LeavePolicyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit()
    {
        $this->authorize('configurePolicy', \App\Models\LeaveBalance::class);

        return view('admin.leave.policy', [
            'types' => LeaveTypeRecord::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(UpdateLeaveEntitlementsRequest $request)
    {
        foreach ($request->validated('types') as $row) {
            LeaveTypeRecord::query()->whereKey($row['id'])->update([
                'entitlement_days' => $row['entitlement_days'],
            ]);
        }

        $this->audit->log($request->user(), 'leave_policy_updated', 'Leave', null, 'Company default leave policy updated. Existing employee balances were not changed.');

        return back()->with('success', 'Company default policy saved. This applies only when initializing new employee balances.');
    }
}
