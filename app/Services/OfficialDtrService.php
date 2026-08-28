<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\DtrDayRow;
use App\Support\DtrPeriod;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDtrService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly DtrDayPresenter $presenter,
    ) {}

    /**
     * @return array{employee: Employee, period: DtrPeriod, days: list<DtrDayRow>, totals: array}
     */
    public function sheet(Employee $employee, DtrPeriod $period): array
    {
        $employee->loadMissing(['department', 'workSchedule']);
        $schedule = $employee->schedule();
        $records = $this->attendance->rangeDtr($employee, $period->start, $period->end);

        $days = [];
        foreach ($records as $record) {
            $days[] = $this->presenter->present($record, $schedule);
        }

        return [
            'employee' => $employee,
            'period' => $period,
            'days' => $days,
            'totals' => $this->totals($days),
        ];
    }

    /**
     * @param  list<DtrDayRow>  $days
     * @return array{worked_minutes: int, overtime_minutes: int, incomplete: int, present: int}
     */
    public function totals(array $days): array
    {
        $worked = $overtime = $incomplete = $present = 0;

        foreach ($days as $day) {
            $worked += $day->totalMinutes;
            $overtime += $day->overtimeMinutes;
            if ($day->incomplete) {
                $incomplete++;
            }
            if ($day->amIn || $day->pmIn) {
                $present++;
            }
        }

        return [
            'worked_minutes' => $worked,
            'overtime_minutes' => $overtime,
            'incomplete' => $incomplete,
            'present' => $present,
            'worked_label' => $this->presenter->formatHours($worked) ?? '0',
            'overtime_label' => $this->presenter->formatHours($overtime) ?? '—',
        ];
    }

    /**
     * @param  list<DtrDayRow>  $days
     */
    public function exportCsv(Employee $employee, DtrPeriod $period, array $days, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($employee, $period, $days) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Employee', 'Department', 'Cut-Off']);
            fputcsv($handle, [$employee->fullName(), $employee->department?->name, $period->cutoffLabel]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Day', 'AM Time In', 'AM Time Out', 'PM Time In', 'PM Time Out', 'Overtime', 'Total Hours']);

            foreach ($days as $day) {
                fputcsv($handle, [
                    $day->dateLabel,
                    $day->dayName,
                    $day->amIn ?? '',
                    $day->amOut ?? '',
                    $day->pmIn ?? '',
                    $day->pmOut ?? '',
                    $day->overtime ?? '',
                    $day->totalHours ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<DtrDayRow>  $days
     */
    public function exportExcel(Employee $employee, DtrPeriod $period, array $days, string $filename)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DTR');
        $sheet->fromArray(['Employee', $employee->fullName(), 'Department', $employee->department?->name, 'Cut-Off', $period->cutoffLabel], null, 'A1');
        $sheet->fromArray(['Date', 'Day', 'AM Time In', 'AM Time Out', 'PM Time In', 'PM Time Out', 'Overtime', 'Total Hours'], null, 'A3');

        $i = 4;
        foreach ($days as $day) {
            $sheet->fromArray([
                $day->dateLabel,
                $day->dayName,
                $day->amIn ?? '',
                $day->amOut ?? '',
                $day->pmIn ?? '',
                $day->pmOut ?? '',
                $day->overtime ?? '',
                $day->totalHours ?? '',
            ], null, 'A'.$i);
            $i++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Payload for the official PDF overlay (UI and PDF share this array).
     *
     * @param  list<DtrDayRow>  $days
     * @return array<string, mixed>
     */
    public function pdfPayload(Employee $employee, DtrPeriod $period, array $days): array
    {
        return [
            'employee_name' => $employee->fullName(),
            'department' => $employee->department?->name ?: '',
            'cutoff' => $period->cutoffLabel,
            'rows' => Collection::make($days)->map(fn (DtrDayRow $day) => [
                'date' => $day->dateLabel,
                'day' => $day->dayName,
                'am_in' => $day->pdfValue($day->amIn),
                'am_out' => $day->pdfValue($day->amOut),
                'pm_in' => $day->pdfValue($day->pmIn),
                'pm_out' => $day->pdfValue($day->pmOut),
                'overtime' => $day->pdfValue($day->overtime),
                'total' => $day->pdfValue($day->totalHours),
            ])->all(),
        ];
    }
}
