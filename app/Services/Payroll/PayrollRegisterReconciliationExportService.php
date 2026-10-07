<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BACS-style payroll register export for reconciliation with PAYROLL-BACS.xlsx layout.
 */
class PayrollRegisterReconciliationExportService
{
    /** @var list<string> */
    private const BACS_HEADERS = [
        'Employee No.',
        'Employee Name',
        'Basic Pay',
        'ALU (Abs/Late/UT)',
        'Total Basic Pay',
        'OT Pay',
        'Holiday/Premium',
        'De Minimis',
        'Other Earnings',
        'Gross Compensation',
        'SSS',
        'PhilHealth',
        'HDMF',
        'Other Deductions',
        'Total Deductions',
        'Net Pay',
    ];

    /**
     * @param  Collection<int, \App\Models\PayrollEmployee>  $rows
     */
    public function export(PayrollPeriod $period, Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Register');
        $sheet->fromArray([$period->period_name], null, 'A1');
        $sheet->fromArray([
            $period->start_date->toDateString().' to '.$period->end_date->toDateString(),
        ], null, 'A2');
        $sheet->fromArray(self::BACS_HEADERS, null, 'A4');

        $i = 5;
        $totals = array_fill_keys(array_keys($this->emptyTotals()), 0.0);

        foreach ($rows as $row) {
            $row->loadMissing(['deductions', 'earnings']);
            $alu = (float) $row->absence_deduction + (float) $row->late_deduction + (float) $row->undertime_deduction;
            $holidayPremium = (float) $row->holiday_pay + (float) $row->premium_pay;
            $deductions = $this->deductionMap($row);
            $otherDed = max(0, (float) $row->total_deductions - $alu - $deductions['sss'] - $deductions['philhealth'] - $deductions['hdmf']);

            $line = [
                $row->employee_number,
                $row->employee_name,
                (float) $row->basic_pay,
                $alu,
                (float) $row->total_basic_pay,
                (float) $row->overtime_pay,
                $holidayPremium,
                (float) $row->de_minimis,
                (float) $row->other_earnings,
                (float) $row->gross_compensation,
                $deductions['sss'],
                $deductions['philhealth'],
                $deductions['hdmf'],
                $otherDed,
                (float) $row->total_deductions,
                (float) $row->net_pay,
            ];

            $sheet->fromArray($line, null, 'A'.$i);
            $this->accumulateTotals($totals, $line);
            $i++;
        }

        if ($rows->isNotEmpty()) {
            $sheet->fromArray(array_merge(['TOTALS', $rows->count().' employees'], array_slice(array_values($totals), 0)), null, 'A'.$i);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'payroll-reconciliation-'.$period->end_date->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{sss: float, philhealth: float, hdmf: float}
     */
    private function deductionMap(object $row): array
    {
        $map = ['sss' => 0.0, 'philhealth' => 0.0, 'hdmf' => 0.0];

        foreach ($row->deductions ?? [] as $deduction) {
            $code = $deduction->code ?? $deduction->deductionType?->code ?? '';
            if (isset($map[$code])) {
                $map[$code] += (float) $deduction->amount;
            }
        }

        return $map;
    }

    /** @return array<string, float> */
    private function emptyTotals(): array
    {
        return [
            'basic' => 0,
            'alu' => 0,
            'total_basic' => 0,
            'ot' => 0,
            'holiday' => 0,
            'de_minimis' => 0,
            'other_earnings' => 0,
            'gross' => 0,
            'sss' => 0,
            'philhealth' => 0,
            'hdmf' => 0,
            'other_ded' => 0,
            'total_ded' => 0,
            'net' => 0,
        ];
    }

    /**
     * @param  array<string, float>  $totals
     * @param  list<int|float|string|null>  $line
     */
    private function accumulateTotals(array &$totals, array $line): void
    {
        $keys = array_keys($totals);
        foreach ($keys as $index => $key) {
            $totals[$key] += (float) ($line[$index + 2] ?? 0);
        }
    }
}
