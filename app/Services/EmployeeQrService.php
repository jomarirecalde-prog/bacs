<?php

namespace App\Services;

use App\Enums\QrTokenStatus;
use App\Models\Employee;
use App\Models\EmployeeQrToken;
use App\Support\ManilaTime;
use App\Support\SecureHash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class EmployeeQrService
{
    public const PREFIX = 'bacs_emp_qr_';

    public function issue(Employee $employee, bool $revokeExisting = false): EmployeeQrToken
    {
        if ($revokeExisting) {
            $this->revokeActive($employee);
        }

        $plain = self::PREFIX.SecureHash::randomToken(24);

        return EmployeeQrToken::query()->create([
            'employee_id' => $employee->id,
            'token_hash' => SecureHash::make($plain),
            'token_encrypted' => Crypt::encryptString($plain),
            'status' => QrTokenStatus::Active,
            'generated_at' => ManilaTime::now(),
        ]);
    }

    public function ensure(Employee $employee): EmployeeQrToken
    {
        $current = $employee->activeQrToken();

        return $current ?: $this->issue($employee);
    }

    public function regenerate(Employee $employee): EmployeeQrToken
    {
        return $this->issue($employee, true);
    }

    public function disable(Employee $employee): void
    {
        $token = $employee->activeQrToken();
        if ($token) {
            $token->update(['status' => QrTokenStatus::Disabled]);
        }
    }

    public function enable(Employee $employee): void
    {
        $token = $employee->qrTokens()
            ->where('status', QrTokenStatus::Disabled)
            ->latest('generated_at')
            ->first();

        if ($token) {
            $token->update(['status' => QrTokenStatus::Active, 'revoked_at' => null]);
        } else {
            $this->ensure($employee);
        }
    }

    public function resolve(string $plain): EmployeeQrToken
    {
        $plain = trim($plain);

        if ($plain === '' || ! str_starts_with($plain, self::PREFIX)) {
            throw ValidationException::withMessages([
                'token' => 'This QR code is not registered in the BACS DTR System.',
            ]);
        }

        $token = EmployeeQrToken::query()
            ->with(['employee.user', 'employee.department'])
            ->where('token_hash', SecureHash::make($plain))
            ->first();

        if (! $token || $token->status === QrTokenStatus::Revoked) {
            throw ValidationException::withMessages([
                'token' => 'This QR code is not registered in the BACS DTR System.',
            ]);
        }

        if ($token->status === QrTokenStatus::Disabled) {
            throw ValidationException::withMessages([
                'token' => 'This employee QR code has been disabled.',
            ]);
        }

        return $token;
    }

    public function markUsed(EmployeeQrToken $token): void
    {
        $token->update(['last_used_at' => ManilaTime::now()]);
    }

    private function revokeActive(Employee $employee): void
    {
        $employee->qrTokens()
            ->whereIn('status', [QrTokenStatus::Active, QrTokenStatus::Disabled])
            ->update([
                'status' => QrTokenStatus::Revoked,
                'revoked_at' => ManilaTime::now(),
            ]);
    }
}
