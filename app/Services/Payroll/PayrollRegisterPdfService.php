<?php

namespace App\Services\Payroll;

use App\Models\PayrollPeriod;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class PayrollRegisterPdfService
{
    /**
     * @param  Collection<int, \App\Models\PayrollEmployee>  $rows
     */
    public function download(PayrollPeriod $period, Collection $rows, array $totals): Response
    {
        $filename = sprintf('payroll-register-%s.pdf', $period->end_date->format('Y-m-d'));

        return Pdf::loadView('reports.pdf.payroll-register', [
            'company' => Setting::get('company_name', config('bacs.company_name')),
            'period' => $period,
            'rows' => $rows,
            'totals' => $totals,
        ])->setPaper('legal', 'landscape')->download($filename);
    }
}
