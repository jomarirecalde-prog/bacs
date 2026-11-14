<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompletePasswordSetupRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DataPrivacyConsentService;
use App\Services\PasswordResetLinkService;
use App\Support\ManilaTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetLinkService $resetLinks,
        private readonly AuditLogger $auditLogger,
        private readonly DataPrivacyConsentService $consentService,
    ) {}

    public function show(Request $request, User $user): View
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($this->resetLinks->userEligibleForReset($user), 403, 'This password reset link is no longer valid.');

        return view('auth.reset-password', [
            'user' => $user,
            'submitUrl' => route('password.reset.store', ['user' => $user->id]).'?'.$request->getQueryString(),
        ]);
    }

    public function store(CompletePasswordSetupRequest $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($this->resetLinks->userEligibleForReset($user), 403, 'This password reset link is no longer valid.');

        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'password_changed_at' => ManilaTime::now(),
        ]);

        $this->auditLogger->log($user, 'password_reset_completed', 'Auth', $user->id, 'Password reset via self-service link.');

        Auth::login($user);
        $request->session()->regenerate();

        if (! $this->consentService->hasCurrentConsent($user)) {
            return redirect()->route('privacy.consent.show');
        }

        return redirect()->route('home')->with('success', 'Your password was updated.');
    }
}
