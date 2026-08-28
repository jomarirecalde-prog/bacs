<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Support\ManilaTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function query(Request $request)
    {
        return Attendance::query()
            ->with(['employee.department:id,name'])
            ->when($request->filled('date'), fn ($q) => $q->onDate((string) $request->string('date')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('attendance_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $end = ManilaTime::parse((string) $request->string('date_to'))->addDay()->toDateString();
                $q->where('attendance_date', '<', $end);
            })
            ->when($request->filled('month') && $request->filled('year'), function ($q) use ($request) {
                $start = sprintf('%04d-%02d-01', $request->integer('year'), $request->integer('month'));
                $end = date('Y-m-t', strtotime($start));
                $q->betweenDates($start, $end);
            })
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->integer('department_id')));
            })
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('attendance_date')
            ->orderBy('employee_id');
    }

    public function late(Request $request)
    {
        return $this->query($request)->where('late_minutes', '>', 0);
    }

    public function absences(Request $request)
    {
        return $this->query($request)->where('status', AttendanceStatus::Absent);
    }

    public function overtime(Request $request)
    {
        return $this->query($request)->where('overtime_minutes', '>', 0);
    }

    public function undertime(Request $request)
    {
        return $this->query($request)->where('undertime_minutes', '>', 0);
    }

    public function monthlyRows(Employee $employee, int $year, int $month): array
    {
        return $this->attendanceService->monthlyDtr($employee, $year, $month);
    }

    public function exportCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Employee ID', 'Employee', 'Department', 'Date', 'Time In', 'Time Out', 'Hours', 'Late (min)', 'Undertime (min)', 'Overtime (min)', 'Status', 'Remarks']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->employee?->employee_number,
                    $row->employee?->fullName(),
                    $row->employee?->department?->name,
                    optional($row->attendance_date)->toDateString(),
                    ManilaTime::formatTime($row->time_in),
                    ManilaTime::formatTime($row->time_out),
                    $row->totalHoursLabel(),
                    $row->late_minutes,
                    $row->undertime_minutes,
                    $row->overtime_minutes,
                    $row->status?->label(),
                    $row->remarks,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExcel(Collection $rows, string $filename)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DTR Report');
        $headers = ['Employee ID', 'Employee', 'Department', 'Date', 'Time In', 'Time Out', 'Hours', 'Late', 'Undertime', 'Overtime', 'Status', 'Remarks'];
        $sheet->fromArray($headers, null, 'A1');

        $i = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row->employee?->employee_number,
                $row->employee?->fullName(),
                $row->employee?->department?->name,
                optional($row->attendance_date)->toDateString(),
                ManilaTime::formatTime($row->time_in),
                ManilaTime::formatTime($row->time_out),
                $row->totalHoursLabel(),
                $row->late_minutes,
                $row->undertime_minutes,
                $row->overtime_minutes,
                $row->status?->label(),
                $row->remarks,
            ], null, 'A'.$i);
            $i++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(string $view, array $data, string $filename)
    {
        $data['company'] = Setting::get('company_name', 'BACS');
        $data['address'] = Setting::get('company_address', '');
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
