<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\PayrollPremiumRule;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\PayrollSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PayrollSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('create', PayrollPeriod::class);

        return view('admin.payroll.settings.index', [
            'rules' => PayrollPremiumRule::query()->orderBy('sort_order')->get(),
            'otMultiplier' => PayrollSettings::overtimeMultiplier(),
            'holidayPayMode' => PayrollSettings::holidayPayMode(),
            'otRequiresApproval' => PayrollSettings::overtimeRequiresApproval(),
            'philhealthRate' => PayrollSettings::philhealthRate(),
            'hdmfAmount' => PayrollSettings::hdmfAmount(),
            'workingDaysBasis' => PayrollSettings::workingDaysBasis(),
            'sssFromBrackets' => PayrollSettings::sssFromBracketTable(),
            'blockFinalizeOnWarnings' => PayrollSettings::blockFinalizeOnWarnings(),
            'emailPayslipsOnPaid' => PayrollSettings::emailPayslipsOnPaid(),
            'otCentralApproval' => PayrollSettings::overtimeUseCentralApproval(),
            'taxFromBrackets' => PayrollSettings::taxFromBracketTable(),
            'unworkedHolidayPay' => PayrollSettings::payUnworkedRegularHolidays(),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'payroll_overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'payroll_holiday_pay_mode' => ['required', 'in:premium_only,full_multiplier'],
            'payroll_ot_requires_approval' => ['nullable', 'boolean'],
            'payroll_philhealth_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payroll_hdmf_amount' => ['nullable', 'numeric', 'min:0'],
            'payroll_working_days_basis' => ['required', 'integer', 'min:1', 'max:31'],
            'payroll_sss_from_brackets' => ['nullable', 'boolean'],
            'payroll_block_finalize_on_warnings' => ['nullable', 'boolean'],
            'payroll_email_on_paid' => ['nullable', 'boolean'],
            'payroll_ot_central_approval' => ['nullable', 'boolean'],
            'payroll_tax_from_brackets' => ['nullable', 'boolean'],
            'payroll_unworked_regular_holiday_pay' => ['nullable', 'boolean'],
        ]);

        Setting::put('payroll_overtime_multiplier', (string) $data['payroll_overtime_multiplier']);
        Setting::put('payroll_holiday_pay_mode', $data['payroll_holiday_pay_mode']);
        Setting::put('payroll_ot_requires_approval', $request->boolean('payroll_ot_requires_approval') ? '1' : '0');
        Setting::put('payroll_philhealth_rate', (string) ($data['payroll_philhealth_rate'] ?? 0));
        Setting::put('payroll_hdmf_amount', (string) ($data['payroll_hdmf_amount'] ?? 0));
        Setting::put('payroll_working_days_basis', (string) $data['payroll_working_days_basis']);
        Setting::put('payroll_sss_from_brackets', $request->boolean('payroll_sss_from_brackets') ? '1' : '0');
        Setting::put('payroll_block_finalize_on_warnings', $request->boolean('payroll_block_finalize_on_warnings') ? '1' : '0');
        Setting::put('payroll_email_on_paid', $request->boolean('payroll_email_on_paid') ? '1' : '0');
        Setting::put('payroll_ot_central_approval', $request->boolean('payroll_ot_central_approval') ? '1' : '0');
        Setting::put('payroll_tax_from_brackets', $request->boolean('payroll_tax_from_brackets') ? '1' : '0');
        Setting::put('payroll_unworked_regular_holiday_pay', $request->boolean('payroll_unworked_regular_holiday_pay') ? '1' : '0');
        Cache::forget('app_settings');

        $this->audit->log($request->user(), 'payroll_settings_updated', 'Payroll', null, 'Payroll general settings updated.');

        return back()->with('success', 'Payroll settings saved. Recompute payroll for changes to apply.');
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
}
