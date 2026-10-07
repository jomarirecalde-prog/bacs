<div class="doc-header">
    <h1>{{ $company }}</h1>
    <div class="muted">{{ $address }}</div>
</div>
<div class="doc-rule-gold"></div>

<h3>PAYSLIP</h3>
<p class="meta">
    <strong>{{ $row->employee_name }}</strong> ({{ $row->employee_number }})<br>
    Department: {{ $row->department_name ?? '—' }} · Designation: {{ $row->designation_name ?? '—' }}<br>
    Pay period: {{ $period->start_date->format('M j, Y') }} – {{ $period->end_date->format('M j, Y') }}
    @if ($period->payroll_date ?? null)
        <br>Payroll date: {{ $period->payroll_date->format('M j, Y') }}
    @endif
</p>
