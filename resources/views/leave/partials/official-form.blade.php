{{-- Official BACS Leave Form. Preserve document layout — do not apply the app theme. --}}
@php
    $app = $application ?? null;
    $emp = $employee ?? $app?->employee;
    $entitlements = $entitlements ?? collect();
    $balances = $balances ?? [];
    $supervisor = $supervisor ?? [];
    $departmentHead = $departmentHead ?? [];
    $adminHead = $adminHead ?? [];
    $hrOfficer = $hrOfficer ?? [];
    $box = fn (bool $on) => $on ? 'X' : '';
    $ent = function (string $code, int $fallback) use ($entitlements) {
        $record = is_array($entitlements) ? ($entitlements[$code] ?? null) : $entitlements[$code] ?? null;

        return (int) (is_object($record) ? $record->entitlement_days : ($record['entitlement_days'] ?? $fallback));
    };
    $vlTaken = $app?->hr_leave_taken;
    $vlBalance = $app?->hr_leave_balance;
    if ($vlTaken === null && isset($balances['vacation'])) {
        $vlTaken = $balances['vacation']['used'];
        $vlBalance = $balances['vacation']['remaining'];
    }
    $silDate = $app?->hr_sil_as_of?->format('m/d/Y') ?: ($app?->date_filed ? $app->date_filed->timezone('Asia/Manila')->format('m/d/Y') : '');
    $silDays = $app?->hr_sil_balance ?? ($balances['vacation']['remaining'] ?? '');
    $logo = public_path('images/bacs_logo_no_bg.png');
    $hasLogo = is_file($logo);
@endphp
<div class="lf">
    <table class="lf-head">
        <tr>
            <td class="lf-logo">
                @if ($hasLogo)
                    <img src="{{ $logo }}" alt="BACS">
                @endif
            </td>
            <td class="lf-company">
                <div class="lf-co">BACS CONSTRUCTION AND DEVELOPMENT CORPORATION</div>
                <div class="lf-sub">{{ \App\Models\Setting::get('company_address', 'Puerto Princesa City, Palawan, Philippines') }}</div>
            </td>
            <td class="lf-meta">
                @if ($app)
                    <div>No. {{ $app->application_number }}</div>
                    <div>{{ $app->status?->label() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="lf-grid">
        <tr>
            <td class="lbl" width="18%">NAME</td>
            <td class="val" width="32%">{{ $emp?->fullName() }}</td>
            <td class="lbl" width="18%">DEPARTMENT</td>
            <td class="val" width="32%">{{ $emp?->department?->name }}</td>
        </tr>
        <tr>
            <td class="lbl">EMPLOYEE ID</td>
            <td class="val">{{ $emp?->employee_number }}</td>
            <td class="lbl">POSITION</td>
            <td class="val">{{ $emp?->position }}</td>
        </tr>
    </table>

    <div class="lf-banner">LEAVE DETAILS</div>

    <table class="lf-grid">
        <tr>
            <td width="42%" class="top">
                <div class="lbl-inline">NO OF DAYS REQUESTED:</div>
                <div class="days-line">
                    <span class="days-val">{{ $app?->requested_days !== null ? rtrim(rtrim(number_format((float) $app->requested_days, 1), '0'), '.') : '' }}</span>
                    <span>days</span>
                </div>
            </td>
            <td width="58%" class="top">
                <div class="lbl-inline">PARTICULAR LEAVE (Check the Box)</div>
                <table class="lf-checks">
                    <tr>
                        <td><span class="chk">{{ $box($app?->isChecked(\App\Enums\LeaveType::Vacation) ?? false) }}</span> VACATION LEAVE</td>
                        <td><span class="chk">{{ $box($app?->isChecked(\App\Enums\LeaveType::Birthday) ?? false) }}</span> BIRTHDAY LEAVE</td>
                    </tr>
                    <tr>
                        <td><span class="chk">{{ $box($app?->isChecked(\App\Enums\LeaveType::Sick) ?? false) }}</span> SICK LEAVE</td>
                        <td>
                            <span class="chk">{{ $box($app?->isChecked(\App\Enums\LeaveType::Special) ?? false) }}</span> SPECIAL LEAVE
                            <div class="special-note">(Maternity, Paternity, Magna Carta<br>for Women, VAWC, Solo Parent)</div>
                            @if ($app?->special_leave_type)
                                <div class="special-sel">Selected: {{ $app->special_leave_type->label() }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><span class="chk">{{ $box($app?->isChecked(\App\Enums\LeaveType::Bereavement) ?? false) }}</span> BEREAVEMENT LEAVE</td>
                        <td></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <strong>DATES OF LEAVE</strong> (mm/dd/yyyy):
                &nbsp; Fr: <span class="u">{{ $app?->start_date?->format('m/d/Y') }}</span>
                &nbsp; To: <span class="u">{{ $app?->end_date?->format('m/d/Y') }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <strong>REASON:</strong>
                <div class="reason">{{ $app?->reason }}</div>
            </td>
        </tr>
    </table>

    <p class="declare">
        I hereby request for my Service Incentive Leave in accordance with company policy. I certify that the
        information provided above is true and accurate to the best of my knowledge.
    </p>

    <table class="lf-grid">
        <tr>
            <td width="65%" class="sign-cell">
                <div class="lbl-inline">EMPLOYEE’S PRINT NAME &amp; SIGNATURE</div>
                @if (!empty($signatureSrc))
                    <img src="{{ $signatureSrc }}" alt="Signature" class="sig-img">
                @endif
                <div class="print-name">{{ $app?->employee_print_name ?? $emp?->fullName() }}</div>
            </td>
            <td width="35%" class="sign-cell">
                <div class="lbl-inline">DATE FILED</div>
                <div class="print-name">{{ $app?->date_filed ? $app->date_filed->timezone('Asia/Manila')->format('m/d/Y g:i A') : '' }}</div>
            </td>
        </tr>
    </table>

    <div class="lf-banner">IMMEDIATE SUPERVISOR/SUPERIOR APPROVAL</div>
    <p class="declare tight">I hereby approve the request for Service Incentive Leave as indicated above.</p>
    <table class="lf-grid">
        <tr>
            <td class="dec-cell" width="22%">
                <span class="chk">{{ $box($supervisor['approved'] ?? false) }}</span> APPROVED<br>
                <span class="chk">{{ $box($supervisor['denied'] ?? false) }}</span> DENIED
            </td>
            <td>
                <div class="lbl-inline">SUPERVISOR’S NAME &amp; SIGNATURE</div>
                @if (!empty($supervisor['signature']))
                    <img src="{{ $supervisor['signature'] }}" alt="" class="sig-img sm">
                @endif
                <div>{{ $supervisor['name'] ?? '' }}</div>
                @if (!empty($supervisor['rows']) && $supervisor['rows']->count() > 1)
                    <div class="tiny">
                        @foreach ($supervisor['rows'] as $row)
                            {{ $row->approver_name }} — {{ $row->decisionLabel() }}@if (!$loop->last); @endif
                        @endforeach
                    </div>
                @endif
            </td>
            <td width="18%">
                <div class="lbl-inline">DATE:</div>
                {{ $supervisor['date'] ?? '' }}
            </td>
            <td width="22%">
                <div class="lbl-inline">REASON:</div>
                {{ $supervisor['reason'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td class="dec-cell">
                <span class="chk">{{ $box($departmentHead['approved'] ?? false) }}</span> APPROVED<br>
                <span class="chk">{{ $box($departmentHead['denied'] ?? false) }}</span> DENIED
            </td>
            <td>
                <div class="lbl-inline">DEPARTMENT HEAD NAME &amp; SIGNATURE</div>
                @if (!empty($departmentHead['signature']))
                    <img src="{{ $departmentHead['signature'] }}" alt="" class="sig-img sm">
                @endif
                <div>{{ $departmentHead['name'] ?? '' }}</div>
            </td>
            <td>
                <div class="lbl-inline">DATE:</div>
                {{ $departmentHead['date'] ?? '' }}
            </td>
            <td>
                <div class="lbl-inline">REASON:</div>
                {{ $departmentHead['reason'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td class="dec-cell">
                <span class="chk">{{ $box($adminHead['approved'] ?? false) }}</span> APPROVED<br>
                <span class="chk">{{ $box($adminHead['denied'] ?? false) }}</span> DENIED
            </td>
            <td>
                <div class="lbl-inline">ADMINISTRATIVE HEAD NAME &amp; SIGNATURE</div>
                @if (!empty($adminHead['signature']))
                    <img src="{{ $adminHead['signature'] }}" alt="" class="sig-img sm">
                @endif
                <div>{{ $adminHead['name'] ?? '' }}</div>
            </td>
            <td>
                <div class="lbl-inline">DATE:</div>
                {{ $adminHead['date'] ?? '' }}
            </td>
            <td>
                <div class="lbl-inline">REASON:</div>
                {{ $adminHead['reason'] ?? '' }}
            </td>
        </tr>
    </table>

    <div class="lf-banner">For HR use only</div>
    <table class="lf-grid">
        <tr>
            <td width="48%" class="top">
                <div><strong>SIL BALANCE</strong> as of (date): <span class="u">{{ $silDate }}</span></div>
                <div class="days-line"><span class="days-val">{{ $silDays !== '' && $silDays !== null ? rtrim(rtrim(number_format((float) $silDays, 1), '0'), '.') : '' }}</span> days</div>
                <p class="note">Once completed, please submit this form to HR/Admin Department for processing. Thank you.</p>
                <div class="pay">
                    <span class="chk">{{ $box(($app?->payment_type?->value ?? '') === 'with_pay') }}</span> LEAVE WITH PAY
                    &nbsp;&nbsp;
                    <span class="chk">{{ $box(($app?->payment_type?->value ?? '') === 'without_pay') }}</span> LEAVE W/O PAY
                </div>
            </td>
            <td width="52%" class="top">
                <div class="ent-title">LEAVE ENTITLEMENT</div>
                <div><strong>VACATION LEAVE:</strong> {{ $ent('vacation', 5) }} DAYS / DAYS</div>
                <div>Leave Taken: <span class="u">{{ $vlTaken !== null ? rtrim(rtrim(number_format((float) $vlTaken, 1), '0'), '.') : '' }}</span> DAYS</div>
                <div>Leave Balance: <span class="u">{{ $vlBalance !== null ? rtrim(rtrim(number_format((float) $vlBalance, 1), '0'), '.') : '' }}</span> DAYS</div>
                <table class="ent-grid">
                    <tr>
                        <td>SICK LEAVE: {{ $ent('sick', 3) }} days</td>
                        <td>MATERNITY LEAVE {{ $ent('maternity', 105) }} days</td>
                    </tr>
                    <tr>
                        <td>BEREAVEMENT LEAVE: {{ $ent('bereavement', 2) }} days</td>
                        <td>PATERNITY LEAVE {{ $ent('paternity', 7) }} days</td>
                    </tr>
                    <tr>
                        <td>BIRTHDAY LEAVE: {{ $ent('birthday', 1) }} day</td>
                        <td>MAGNA CARTA: {{ $ent('magna_carta', 60) }} days</td>
                    </tr>
                    <tr>
                        <td>SOLO PARENT : {{ $ent('solo_parent', 7) }} days</td>
                        <td>VAWC : {{ $ent('vawc', 10) }} days</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <div class="lbl-inline">HR Officer Name &amp; Signature</div>
                @if (!empty($hrOfficer['signature']))
                    <img src="{{ $hrOfficer['signature'] }}" alt="" class="sig-img sm">
                @endif
                <div>{{ $hrOfficer['name'] ?? '' }}</div>
            </td>
            <td>
                <div class="lbl-inline">DATE:</div>
                {{ $hrOfficer['date'] ?? '' }}
            </td>
        </tr>
    </table>

    <table class="lf-foot">
        <tr>
            <td>CC: 201 Files</td>
            <td>Finance Department</td>
            <td>Concern Department</td>
            <td class="right"><strong>LEAVE FORM</strong></td>
        </tr>
    </table>
</div>
