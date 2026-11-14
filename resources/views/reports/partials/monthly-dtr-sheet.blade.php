@props([
    'employee',
    'rows',
    'year',
    'month',
    'company',
    'address' => '',
    'pageBreak' => false,
    'letterheadSrc' => null,
])

@php
    $letterheadSrc ??= asset('images/ot-attachment-letterhead.png');
@endphp

<section @class(['dtr-sheet', 'page-break' => $pageBreak])>
    @if ($letterheadSrc)
        <img src="{{ $letterheadSrc }}" alt="" class="dtr-letterhead">
    @else
        <div class="doc-header">
            <h1>{{ $company }}</h1>
            <div class="muted">{{ $address }}</div>
        </div>
        <div class="doc-rule-gold"></div>
    @endif

    <table class="dtr-title-wrap">
        <tr>
            <td>DAILY TIME RECORD</td>
        </tr>
    </table>
    <p class="meta">
        <strong>{{ $employee->fullName() }}</strong> ({{ $employee->employee_number }})<br>
        {{ $employee->department?->name }} · {{ $employee->position }}<br>
        {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Hours</th>
                <th>Late</th>
                <th>Undertime</th>
                <th>Overtime</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @php $holiday = app(\App\Services\HolidayResolver::class)->forDate(optional($row->attendance_date)->toDateString() ?: now()->toDateString(), $employee); @endphp
                    <td>
                        {{ optional($row->attendance_date)->format('M d, Y D') }}
                        @if ($holiday)<br><span class="muted">{{ $holiday->name }}</span>@endif
                    </td>
                    <td>{{ $row->time_in?->format('h:i A') }}</td>
                    <td>{{ $row->time_out?->format('h:i A') }}</td>
                    <td class="num">{{ $row->totalHoursLabel() }}</td>
                    <td class="num">{{ $row->late_minutes }}</td>
                    <td class="num">{{ $row->undertime_minutes }}</td>
                    <td class="num">{{ $row->overtimeHoursLabel() }}</td>
                    <td>{{ $row->status?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td><div class="line">Employee Signature</div></td>
            <td><div class="line">Authorized Official</div></td>
        </tr>
    </table>
</section>