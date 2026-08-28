<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\OfficialDtrPdfService;
use App\Services\OfficialDtrService;
use App\Support\DtrPeriod;
use Illuminate\Http\Request;

class DtrController extends Controller
{
    public function __construct(
        private readonly OfficialDtrService $dtr,
        private readonly OfficialDtrPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $period = DtrPeriod::fromRequest($request);
        $sheet = $this->dtr->sheet($employee, $period);

        return view('employee.dtr', [
            'employee' => $sheet['employee'],
            'period' => $sheet['period'],
            'days' => $sheet['days'],
            'totals' => $sheet['totals'],
            'periods' => DtrPeriod::options(),
        ]);
    }

    public function export(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $period = DtrPeriod::fromRequest($request);
        $sheet = $this->dtr->sheet($employee, $period);
        $format = $request->string('format')->toString() ?: 'pdf';
        $stamp = $period->start.'_'.$period->end;

        if ($format === 'excel') {
            return $this->dtr->exportExcel($employee, $period, $sheet['days'], "my-dtr-{$stamp}.xlsx");
        }
        if ($format === 'csv') {
            return $this->dtr->exportCsv($employee, $period, $sheet['days'], "my-dtr-{$stamp}.csv");
        }

        return $this->pdf->download($employee, $period, $sheet['days']);
    }

    public function print(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $period = DtrPeriod::fromRequest($request);
        $sheet = $this->dtr->sheet($employee, $period);

        return $this->pdf->stream($employee, $period, $sheet['days']);
    }

    public function show(Request $request, Employee $employee)
    {
        abort_unless($request->user()->employee?->id === $employee->id, 403);

        return redirect()->route('employee.dtr');
    }

    private function ownEmployee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        if ($request->filled('employee_id')) {
            abort_unless((int) $request->input('employee_id') === (int) $employee->id, 403);
        }

        return $employee->load(['department', 'user', 'workSchedule']);
    }
}
