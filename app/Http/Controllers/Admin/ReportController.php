<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\DirectoryCatalog;
use App\Services\ReportService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly DirectoryCatalog $catalog,
    ) {}

    public function index()
    {
        return view('admin.reports.index');
    }

    public function daily(Request $request)
    {
        $request->merge(['date' => $request->input('date', ManilaTime::todayDate())]);
        $rows = $this->reports->query($request);

        return $this->render('Daily Attendance Report', 'daily', $request, $rows);
    }

    public function monthly(Request $request)
    {
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $employee = $request->filled('employee_id')
            ? Employee::query()->with('department')->findOrFail($request->integer('employee_id'))
            : null;

        $rows = $employee ? collect($this->reports->monthlyRows($employee, $year, $month)) : collect();

        if ($request->input('export') === 'pdf' && $employee) {
            return $this->reports->exportPdf('reports.pdf.monthly-dtr', [
                'title' => 'Monthly DTR',
                'employee' => $employee,
                'rows' => $rows,
                'year' => $year,
                'month' => $month,
            ], "monthly-dtr-{$employee->employee_number}-{$year}-{$month}.pdf");
        }

        if ($request->input('export') === 'excel' && $employee) {
            $rows->each(fn ($row) => $row->setRelation('employee', $employee));

            return $this->reports->exportExcel($rows, "monthly-dtr-{$employee->employee_number}-{$year}-{$month}.xlsx");
        }

        if ($request->input('export') === 'csv' && $employee) {
            $rows->each(fn ($row) => $row->setRelation('employee', $employee));

            return $this->reports->exportCsv($rows, "monthly-dtr-{$employee->employee_number}-{$year}-{$month}.csv");
        }

        return view('admin.reports.show', [
            'title' => 'Monthly DTR',
            'type' => 'monthly',
            'rows' => $rows,
            'employee' => $employee,
            'filters' => $this->filterPayload($request),
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function late(Request $request)
    {
        return $this->render('Late Employees Report', 'late', $request, $this->reports->late($request));
    }

    public function absences(Request $request)
    {
        return $this->render('Absence Report', 'absences', $request, $this->reports->absences($request));
    }

    public function overtime(Request $request)
    {
        return $this->render('Overtime Report', 'overtime', $request, $this->reports->overtime($request));
    }

    public function undertime(Request $request)
    {
        return $this->render('Undertime Report', 'undertime', $request, $this->reports->undertime($request));
    }

    private function render(string $title, string $type, Request $request, $query)
    {
        $maxRows = (int) config('performance.report_export_max_rows', 5000);

        if (in_array($request->input('export'), ['excel', 'csv', 'pdf'], true)) {
            $count = (clone $query)->count();
            if ($count > $maxRows) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Export limited to {$maxRows} rows. Narrow the date range or filters (found {$count} rows).");
            }

            if ($request->input('export') === 'excel') {
                return $this->reports->exportExcel($query->get(), $type.'-report.xlsx');
            }
            if ($request->input('export') === 'csv') {
                return $this->reports->exportCsv($query->get(), $type.'-report.csv');
            }

            return $this->reports->exportPdf('reports.pdf.attendance', [
                'title' => $title,
                'rows' => $query->get(),
            ], $type.'-report.pdf');
        }

        return view('admin.reports.show', [
            'title' => $title,
            'type' => $type,
            'rows' => $query->paginate(25)->withQueryString(),
            'filters' => $this->filterPayload($request),
        ]);
    }

    private function filterPayload(Request $request): array
    {
        return [
            'departments' => $this->catalog->departments(),
            'employees' => $this->catalog->employeeOptions(),
            'statuses' => AttendanceStatus::cases(),
            'values' => $request->all(),
        ];
    }
}
