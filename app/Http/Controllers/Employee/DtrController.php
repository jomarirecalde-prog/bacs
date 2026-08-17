<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\ReportService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class DtrController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $rows = $this->attendanceService->monthlyDtr($employee, $year, $month);

        return view('employee.dtr', compact('employee', 'rows', 'year', 'month'));
    }

    public function export(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $rows = collect($this->attendanceService->monthlyDtr($employee, $year, $month));
        $rows->each(fn ($row) => $row->setRelation('employee', $employee));

        $format = $request->string('format')->toString() ?: 'pdf';

        if ($format === 'excel') {
            return $this->reports->exportExcel($rows, "my-dtr-{$year}-{$month}.xlsx");
        }
        if ($format === 'csv') {
            return $this->reports->exportCsv($rows, "my-dtr-{$year}-{$month}.csv");
        }

        return $this->reports->exportPdf('reports.pdf.monthly-dtr', [
            'title' => 'Monthly Daily Time Record',
            'employee' => $employee,
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'company' => Setting::get('company_name', 'BACS'),
            'address' => Setting::get('company_address', ''),
        ], "my-dtr-{$year}-{$month}.pdf");
    }

    public function print(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $rows = $this->attendanceService->monthlyDtr($employee, $year, $month);

        return view('reports.print.monthly-dtr', [
            'employee' => $employee,
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'company' => Setting::get('company_name', 'BACS'),
            'address' => Setting::get('company_address', ''),
        ]);
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

        return $employee->load(['department', 'user']);
    }
}
