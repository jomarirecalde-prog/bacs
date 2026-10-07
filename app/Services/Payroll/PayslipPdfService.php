<?php

namespace App\Services\Payroll;

use App\Models\PayrollEmployee;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PayslipPdfService
{
    public function viewData(PayrollEmployee $payrollEmployee): array
    {
        $payrollEmployee->loadMissing(['payrollPeriod', 'earnings', 'deductions']);

        return [
            'company' => Setting::get('company_name', config('bacs.company_name')),
            'address' => Setting::get('company_address', ''),
            'row' => $payrollEmployee,
            'period' => $payrollEmployee->payrollPeriod,
        ];
    }

    public function download(PayrollEmployee $payrollEmployee): Response
    {
        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $payrollEmployee->employee_number,
            $payrollEmployee->payrollPeriod?->end_date?->format('Y-m-d') ?? 'period'
        );

        return $this->pdf($payrollEmployee)->download($filename);
    }

    public function stream(PayrollEmployee $payrollEmployee): Response
    {
        return $this->pdf($payrollEmployee)->stream('payslip.pdf');
    }

    private function pdf(PayrollEmployee $payrollEmployee): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('reports.pdf.payslip', $this->viewData($payrollEmployee))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');
    }
}
