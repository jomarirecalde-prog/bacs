<?php

namespace App\Mail;

use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Support\EmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollEmployee $payrollEmployee,
        public PayrollPeriod $period,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BACS | Payslip available — '.$this->period->period_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payslip-available',
            with: array_merge(EmailBranding::layoutContext(), [
                'row' => $this->payrollEmployee,
                'period' => $this->period,
                'viewUrl' => route('employee.payroll.show', $this->payrollEmployee),
            ]),
        );
    }
}
