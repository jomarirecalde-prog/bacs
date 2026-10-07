<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollRegisterExportService;
use App\Services\Payroll\PayrollRegisterPdfService;
use App\Services\Payroll\PayrollRegisterReconciliationExportService;
use Illuminate\Http\Request;

class PayrollRegisterController extends Controller
{
    public function __construct(
        private readonly PayrollRegisterExportService $export,
        private readonly PayrollRegisterPdfService $pdf,
        private readonly PayrollRegisterReconciliationExportService $reconciliation,
    ) {}

    public function index(Request $request, PayrollPeriod $period)
    {
        $this->authorize('view', $period);

        $rows = $this->filteredRows($request, $period);

        $totals = [
            'basic_pay' => $rows->sum('basic_pay'),
            'attendance_deduction' => $rows->sum(fn ($r) => $r->absence_deduction + $r->late_deduction + $r->undertime_deduction),
            'total_basic_pay' => $rows->sum('total_basic_pay'),
            'overtime_pay' => $rows->sum('overtime_pay'),
            'holiday_pay' => $rows->sum('holiday_pay'),
            'premium_pay' => $rows->sum('premium_pay'),
            'gross_wage' => $rows->sum('gross_wage'),
            'de_minimis' => $rows->sum('de_minimis'),
            'other_earnings' => $rows->sum('other_earnings'),
            'gross_compensation' => $rows->sum('gross_compensation'),
            'total_deductions' => $rows->sum('total_deductions'),
            'net_pay' => $rows->sum('net_pay'),
        ];

        return view('admin.payroll.register.index', [
            'period' => $period,
            'rows' => $rows,
            'totals' => $totals,
            'designations' => Designation::query()->active()->orderBy('designation_name')->get(['id', 'designation_name']),
            'filters' => $request->only(['q', 'designation_id']),
        ]);
    }

    public function export(Request $request, PayrollPeriod $period)
    {
        $this->authorize('view', $period);

        $rows = $this->filteredRows($request, $period);
        $slug = str($period->period_name)->slug('_')->limit(40);
        $stamp = $period->start_date->format('Y-m-d');

        if ($request->string('format') === 'excel') {
            return $this->export->exportExcel($rows, "payroll-register-{$slug}-{$stamp}.xlsx");
        }

        if ($request->string('format') === 'pdf') {
            $totals = $this->registerTotals($rows);

            return $this->pdf->download($period, $rows, $totals);
        }

        if ($request->string('format') === 'reconciliation') {
            return $this->reconciliation->export($period, $rows);
        }

        return $this->export->exportCsv($rows, "payroll-register-{$slug}-{$stamp}.csv");
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollEmployee>  $rows
     * @return array<string, float>
     */
    private function registerTotals($rows): array
    {
        return [
            'basic_pay' => $rows->sum('basic_pay'),
            'total_basic_pay' => $rows->sum('total_basic_pay'),
            'overtime_pay' => $rows->sum('overtime_pay'),
            'holiday_pay' => $rows->sum('holiday_pay'),
            'premium_pay' => $rows->sum('premium_pay'),
            'gross_wage' => $rows->sum('gross_wage'),
            'gross_compensation' => $rows->sum('gross_compensation'),
            'total_deductions' => $rows->sum('total_deductions'),
            'net_pay' => $rows->sum('net_pay'),
        ];
    }

    private function filteredRows(Request $request, PayrollPeriod $period)
    {
        return PayrollEmployee::query()
            ->where('payroll_period_id', $period->id)
            ->when($request->filled('designation_id'), fn ($q) => $q->where('designation_id', $request->integer('designation_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('employee_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
            })
            ->orderBy('employee_name')
            ->get();
    }
}
