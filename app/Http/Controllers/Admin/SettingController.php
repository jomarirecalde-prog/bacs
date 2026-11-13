<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalTransactionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use App\Services\CentralApprovalWorkflowService;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(CentralApprovalWorkflowService $approvalWorkflows)
    {
        $holidays = Holiday::query()->orderBy('holiday_date')->get();

        $adminAccounts = User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('name')
            ->get();

        $departments = Department::query()->active()->ordered()->get();

        $approvalWorkflows->synchronizeAllTransactionTypesFromCanonical();

        return view('admin.settings.index', [
            'company' => Setting::get('company_name', 'BACS'),
            'address' => Setting::get('company_address', ''),
            'holidays' => $holidays,
            'adminAccounts' => $adminAccounts,
            'approvalWorkflowConfiguration' => $approvalWorkflows->canonicalConfiguration(),
            'approvalUnifiedTransactionTypes' => ApprovalTransactionType::unifiedTypes(),
            'pardonApprovalWorkflowConfiguration' => $approvalWorkflows->configurationFor(ApprovalTransactionType::Pardon),
            'approvalDepartments' => $departments,
            'employeeSearchUrl' => route('admin.settings.approval-workflow.employees.search'),
            'mailSettings' => MailSettings::formState(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::put('company_name', $data['company_name']);
        Setting::put('company_address', $data['company_address'] ?? '');
        Cache::forget('app_settings');

        return back()->with('success', 'Settings saved.');
    }

    public function updateSmtp(Request $request)
    {
        $data = $request->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'log', 'sendmail'])],
            'mail_host' => ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:255'],
            'mail_port' => ['required_if:mail_mailer,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:120'],
        ]);

        Setting::put('mail_mailer', $data['mail_mailer']);
        Setting::put('mail_from_address', $data['mail_from_address']);
        Setting::put('mail_from_name', $data['mail_from_name']);

        if ($data['mail_mailer'] === 'smtp') {
            Setting::put('mail_host', $data['mail_host'] ?? '');
            Setting::put('mail_port', (string) ($data['mail_port'] ?? 587));
            Setting::put('mail_username', $data['mail_username'] ?? '');
            Setting::put('mail_encryption', $data['mail_encryption'] ?? 'tls');

            if (filled($data['mail_password'] ?? null)) {
                Setting::put('mail_password', Crypt::encryptString($data['mail_password']));
            }
        } else {
            Setting::query()->whereIn('key', ['mail_host', 'mail_port', 'mail_username', 'mail_encryption'])->delete();
        }

        Cache::forget('app_settings');
        MailSettings::applyToConfig();

        return redirect()
            ->route('admin.settings.index')
            ->withFragment('settings-smtp')
            ->with('success', 'SMTP settings saved.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'type' => ['required', 'in:regular,special'],
        ]);

        Holiday::query()->create($data);

        return back()->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
