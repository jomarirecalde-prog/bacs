<?php

namespace App\Services\Payroll;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRegisterExportService
{
    /** @var list<string> */
    private const HEADERS = [
        'Employee No.',
        'Employee Name',
        'Department',
        'Designation',
        'Basic Pay',
        'Absence Ded.',
        'Late Ded.',
        'Undertime Ded.',
        'Total Basic Pay',
        'Overtime Pay',
        'Holiday Pay',
        'Premium Pay',
        'Gross Wage',
        'De Minimis',
        'Other Earnings',
        'Gross Compensation',
        'Total Deductions',
        'Net Pay',
    ];

    public function exportCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS);

            foreach ($rows as $row) {
                fputcsv($handle, $this->rowValues($row));
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
        $sheet->setTitle('Payroll Register');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $i = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($this->rowValues($row), null, 'A'.$i);
            $i++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<int|float|string|null>
     */
    private function rowValues(object $row): array
    {
        return [
            $row->employee_number,
            $row->employee_name,
            $row->department_name,
            $row->designation_name,
            (float) $row->basic_pay,
            (float) $row->absence_deduction,
            (float) $row->late_deduction,
            (float) $row->undertime_deduction,
            (float) $row->total_basic_pay,
            (float) $row->overtime_pay,
            (float) $row->holiday_pay,
            (float) $row->premium_pay,
            (float) $row->gross_wage,
            (float) $row->de_minimis,
            (float) $row->other_earnings,
            (float) $row->gross_compensation,
            (float) $row->total_deductions,
            (float) $row->net_pay,
        ];
    }
}
