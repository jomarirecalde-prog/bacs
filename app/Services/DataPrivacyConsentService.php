<?php

namespace App\Services;

use App\Enums\DataPrivacyConsentAction;
use App\Models\DataPrivacyConsent;
use App\Models\User;
use App\Support\PrivacyNotice;
use Illuminate\Http\Request;

class DataPrivacyConsentService
{
    public function currentVersion(): string
    {
        return PrivacyNotice::version();
    }

    public function hasCurrentConsent(User $user): bool
    {
        return DataPrivacyConsent::query()
            ->where('user_id', $user->id)
            ->where('notice_version', $this->currentVersion())
            ->where('action', DataPrivacyConsentAction::Granted)
            ->exists();
    }

    public function record(
        User $user,
        DataPrivacyConsentAction $action,
        Request $request,
    ): DataPrivacyConsent {
        return DataPrivacyConsent::query()->create([
            'user_id' => $user->id,
            'notice_version' => $this->currentVersion(),
            'action' => $action,
            'consented_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notice_text_hash' => PrivacyNotice::textHash(),
        ]);
    }

    public function grantForTesting(User $user): void
    {
        if ($this->hasCurrentConsent($user)) {
            return;
        }

        DataPrivacyConsent::query()->create([
            'user_id' => $user->id,
            'notice_version' => $this->currentVersion(),
            'action' => DataPrivacyConsentAction::Granted,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'BACS Test',
            'notice_text_hash' => PrivacyNotice::textHash(),
        ]);
    }
}
