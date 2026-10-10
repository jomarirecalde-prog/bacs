<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollDeductionType;
use App\Models\PayrollPeriod;
use App\Models\PayrollPremiumRule;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\PayrollDeductionConfig;
use App\Support\PayrollSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PayrollSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('create', PayrollPeriod::class);

        $deductionConfig = PayrollDeductionConfig::forDate(now()->toDateString());
        $latestRevision = PayrollDeductionConfig::latestRevision();

        return view('admin.payroll.settings.index', [
            'rules' => PayrollPremiumRule::query()->orderBy('sort_order')->get(),
            'otMultiplier' => PayrollSettings::overtimeMultiplier(),
            'holidayPayMode' => PayrollSettings::holidayPayMode(),
            'otRequiresApproval' => PayrollSettings::overtimeRequiresApproval(),
            'workingDaysBasis' => PayrollSettings::workingDaysBasis(),
            'blockFinalizeOnWarnings' => PayrollSettings::blockFinalizeOnWarnings(),
            'emailPayslipsOnPaid' => PayrollSettings::emailPayslipsOnPaid(),
            'otCentralApproval' => PayrollSettings::overtimeUseCentralApproval(),
            'unworkedHolidayPay' => PayrollSettings::payUnworkedRegularHolidays(),
            'deductionConfig' => $deductionConfig,
            'deductionTypes' => PayrollDeductionType::query()->orderBy('sort_order')->get(),
            'latestDeductionRevision' => $latestRevision,
            'revisionHistory' => array_reverse(PayrollDeductionConfig::revisionHistory()),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'payroll_overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'payroll_holiday_pay_mode' => ['required', 'in:premium_only,full_multiplier'],
            'payroll_ot_requires_approval' => ['nullable', 'boolean'],
            'payroll_working_days_basis' => ['required', 'integer', 'min:1', 'max:31'],
            'payroll_block_finalize_on_warnings' => ['nullable', 'boolean'],
            'payroll_email_on_paid' => ['nullable', 'boolean'],
            'payroll_ot_central_approval' => ['nullable', 'boolean'],
            'payroll_unworked_regular_holiday_pay' => ['nullable', 'boolean'],
        ]);

        Setting::put('payroll_overtime_multiplier', (string) $data['payroll_overtime_multiplier']);
        Setting::put('payroll_holiday_pay_mode', $data['payroll_holiday_pay_mode']);
        Setting::put('payroll_ot_requires_approval', $request->boolean('payroll_ot_requires_approval') ? '1' : '0');
        Setting::put('payroll_working_days_basis', (string) $data['payroll_working_days_basis']);
        Setting::put('payroll_block_finalize_on_warnings', $request->boolean('payroll_block_finalize_on_warnings') ? '1' : '0');
        Setting::put('payroll_email_on_paid', $request->boolean('payroll_email_on_paid') ? '1' : '0');
        Setting::put('payroll_ot_central_approval', $request->boolean('payroll_ot_central_approval') ? '1' : '0');
        Setting::put('payroll_unworked_regular_holiday_pay', $request->boolean('payroll_unworked_regular_holiday_pay') ? '1' : '0');
        Cache::forget('app_settings');

        $this->audit->log($request->user(), 'payroll_settings_updated', 'Payroll', null, 'Payroll general settings updated.');

        return back()->with('success', 'General payroll settings saved.');
    }

    public function updateStatutory(Request $request)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'payroll_philhealth_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payroll_hdmf_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'payroll_auto_sss' => ['nullable', 'boolean'],
            'payroll_auto_philhealth' => ['nullable', 'boolean'],
            'payroll_auto_hdmf' => ['nullable', 'boolean'],
            'payroll_auto_tax' => ['nullable', 'boolean'],
            'payroll_sss_from_brackets' => ['nullable', 'boolean'],
            'payroll_tax_from_brackets' => ['nullable', 'boolean'],
        ]);

        $statutory = [
            'auto_sss' => $request->boolean('payroll_auto_sss'),
            'auto_philhealth' => $request->boolean('payroll_auto_philhealth'),
            'auto_hdmf' => $request->boolean('payroll_auto_hdmf'),
            'auto_tax' => $request->boolean('payroll_auto_tax'),
            'philhealth_rate' => (float) ($data['payroll_philhealth_rate'] ?? 0),
            'hdmf_amount' => (float) ($data['payroll_hdmf_amount'] ?? 0),
            'sss_from_brackets' => $request->boolean('payroll_sss_from_brackets'),
            'tax_from_brackets' => $request->boolean('payroll_tax_from_brackets'),
        ];

        PayrollDeductionConfig::appendRevision($data['effective_from'], ['statutory' => $statutory], $request->user()?->id);
        $this->syncStatutoryFlatKeys($statutory);

        $this->audit->log(
            $request->user(),
            'payroll_statutory_settings_updated',
            'Payroll',
            null,
            'Statutory contribution settings updated.',
            $request,
            ['effective_from' => $data['effective_from'], 'statutory' => $statutory],
        );

        return back()->with('success', 'Statutory contribution settings saved. Recompute open payroll periods to apply (effective '.$data['effective_from'].'). Finalized or paid periods are unchanged.');
    }

    public function updateLateDeductions(Request $request)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'late_enabled' => ['nullable', 'boolean'],
            'late_calculation_mode' => ['required', Rule::in(['derived_minute_rate', 'fixed_per_minute'])],
            'late_fixed_per_minute' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'late_minimum_billable_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'late_round_minutes' => ['required', Rule::in(['none', 'ceil', 'floor', 'nearest'])],
            'late_minute_rate_from' => ['required', Rule::in(['hourly', 'daily'])],
        ]);

        $this->validateAttendanceModeFields($data, 'late');

        $late = [
            'enabled' => $request->boolean('late_enabled'),
            'calculation_mode' => $data['late_calculation_mode'],
            'fixed_per_minute' => (float) ($data['late_fixed_per_minute'] ?? 0),
            'minimum_billable_minutes' => (int) ($data['late_minimum_billable_minutes'] ?? 0),
            'round_minutes' => $data['late_round_minutes'],
            'minute_rate_from' => $data['late_minute_rate_from'],
        ];

        PayrollDeductionConfig::appendRevision($data['effective_from'], ['late' => $late], $request->user()?->id);

        $this->audit->log(
            $request->user(),
            'payroll_late_deduction_settings_updated',
            'Payroll',
            null,
            'Late deduction settings updated.',
            $request,
            ['effective_from' => $data['effective_from'], 'late' => $late],
        );

        return back()->with('success', 'Late deduction settings saved. Recompute open payroll periods to apply (effective '.$data['effective_from'].').');
    }

    public function updateUndertimeDeductions(Request $request)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'undertime_enabled' => ['nullable', 'boolean'],
            'undertime_calculation_mode' => ['required', Rule::in(['derived_minute_rate', 'fixed_per_minute'])],
            'undertime_fixed_per_minute' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'undertime_minimum_billable_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'undertime_round_minutes' => ['required', Rule::in(['none', 'ceil', 'floor', 'nearest'])],
            'undertime_minute_rate_from' => ['required', Rule::in(['hourly', 'daily'])],
        ]);

        $this->validateAttendanceModeFields($data, 'undertime');

        $undertime = [
            'enabled' => $request->boolean('undertime_enabled'),
            'calculation_mode' => $data['undertime_calculation_mode'],
            'fixed_per_minute' => (float) ($data['undertime_fixed_per_minute'] ?? 0),
            'minimum_billable_minutes' => (int) ($data['undertime_minimum_billable_minutes'] ?? 0),
            'round_minutes' => $data['undertime_round_minutes'],
            'minute_rate_from' => $data['undertime_minute_rate_from'],
        ];

        PayrollDeductionConfig::appendRevision($data['effective_from'], ['undertime' => $undertime], $request->user()?->id);

        $this->audit->log(
            $request->user(),
            'payroll_undertime_deduction_settings_updated',
            'Payroll',
            null,
            'Undertime deduction settings updated.',
            $request,
            ['effective_from' => $data['effective_from'], 'undertime' => $undertime],
        );

        return back()->with('success', 'Undertime deduction settings saved. Recompute open payroll periods to apply (effective '.$data['effective_from'].').');
    }

    public function updateDeductionType(Request $request, PayrollDeductionType $deductionType)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $deductionType->update(['is_active' => (bool) $data['is_active']]);

        $this->audit->log(
            $request->user(),
            'payroll_deduction_type_updated',
            'Payroll',
            $deductionType->id,
            "Deduction type {$deductionType->name} ".($deductionType->is_active ? 'enabled' : 'disabled').'.',
        );

        return back()->with('success', "{$deductionType->name} updated.");
    }

    public function updateRule(Request $request, PayrollPremiumRule $rule)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $rule->update($data);

        return back()->with('success', "Rule {$rule->name} updated.");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateAttendanceModeFields(array $data, string $prefix): void
    {
        $mode = $data["{$prefix}_calculation_mode"] ?? 'derived_minute_rate';

        if ($mode === 'fixed_per_minute' && (float) ($data["{$prefix}_fixed_per_minute"] ?? 0) <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "{$prefix}_fixed_per_minute" => 'Enter a positive amount per minute when using fixed per-minute mode.',
            ]);
        }

    }

    /**
     * @param  array<string, mixed>  $statutory
     */
    private function syncStatutoryFlatKeys(array $statutory): void
    {
        Setting::put('payroll_philhealth_rate', (string) ($statutory['philhealth_rate'] ?? 0));
        Setting::put('payroll_hdmf_amount', (string) ($statutory['hdmf_amount'] ?? 0));
        Setting::put('payroll_sss_from_brackets', ! empty($statutory['sss_from_brackets']) ? '1' : '0');
        Setting::put('payroll_tax_from_brackets', ! empty($statutory['tax_from_brackets']) ? '1' : '0');
        Cache::forget('app_settings');
    }
}
