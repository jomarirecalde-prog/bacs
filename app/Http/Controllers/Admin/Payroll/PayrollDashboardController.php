<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;

class PayrollDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $currentPeriod = PayrollPeriod::query()
            ->whereNotIn('status', [PayrollPeriodStatus::Cancelled])
            ->orderByDesc('start_date')
            ->first();

        $latestComputed = PayrollPeriod::query()
            ->whereHas('payrollEmployees')
            ->orderByDesc('start_date')
            ->first();

        $stats = [
            'employees' => Employee::query()->active()->count(),
            'periods' => PayrollPeriod::query()->count(),
            'pending_review' => PayrollPeriod::query()->where('status', PayrollPeriodStatus::ForReview)->count(),
            'finalized' => PayrollPeriod::query()->whereIn('status', [PayrollPeriodStatus::Finalized, PayrollPeriodStatus::Paid])->count(),
            'net_payroll' => $latestComputed
                ? $latestComputed->payrollEmployees()->sum('net_pay')
                : 0,
        ];

        $recentPeriods = PayrollPeriod::query()
            ->orderByDesc('start_date')
            ->limit(8)
            ->get();

        return view('admin.payroll.dashboard', compact('currentPeriod', 'stats', 'recentPeriods'));
    }
}
