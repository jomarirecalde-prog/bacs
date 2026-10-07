<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompletePasswordSetupRequest;
use App\Models\User;
use App\Services\AccountPasswordSetupService;
use App\Services\AuditLogger;
use App\Support\ManilaTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountPasswordSetupController extends Controller
{
    public function __construct(
        private readonly AccountPasswordSetupService $setupLinks,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request, User $user)
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($this->setupLinks->userEligibleForSetup($user), 403, 'This password setup link is no longer valid.');

        return view('auth.set-password', [
            'user' => $user,
            'submitUrl' => route('password.setup.store', ['user' => $user->id]).'?'.$request->getQueryString(),
        ]);
    }

    public function store(CompletePasswordSetupRequest $request, User $user)
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($this->setupLinks->userEligibleForSetup($user), 403, 'This password setup link is no longer valid.');

        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'password_changed_at' => ManilaTime::now(),
        ]);

        $this->auditLogger->log($user, 'password_setup_completed', 'Auth', $user->id, 'Initial password set via secure link.');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Your password was set. Welcome to BACS.');
    }
}
