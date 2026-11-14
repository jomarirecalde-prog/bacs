<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingController;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Services\AuditLogger;
use App\Services\EmailNotificationService;
use App\Services\PasswordResetLinkService;
use App\Services\UserLoginLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly UserLoginLookupService $loginLookup,
        private readonly PasswordResetLinkService $resetLinks,
        private readonly EmailNotificationService $emailNotifications,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.forgot-password', [
            ...app(LandingController::class)->viewData(),
            'prefillLogin' => request()->string('login')->trim()->toString() ?: old('login'),
        ]);
    }

    public function store(RequestPasswordResetRequest $request): RedirectResponse
    {
        $key = 'password-reset:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many reset attempts. Please try again shortly.',
            ]);
        }

        $login = $request->validated('login');
        $user = $this->loginLookup->findByLogin($login);

        RateLimiter::hit($key, 60);

        $redirect = redirect()
            ->route('password.request')
            ->withInput($request->only('login'));

        if (! $user) {
            return $redirect->with([
                'reset_status' => 'not_found',
                'reset_message' => 'We could not find an account matching that employee ID, username, or email.',
            ]);
        }

        if (! $user->isActive()) {
            return $redirect->with([
                'reset_status' => 'inactive',
                'reset_message' => 'This account is not active. Contact HR or your administrator for assistance.',
            ]);
        }

        if (! $this->resetLinks->userEligibleForReset($user)) {
            return $redirect->with([
                'reset_status' => 'no_email',
                'reset_message' => 'This account does not have a valid email address on file. Contact HR or the DPO to reset your password.',
            ]);
        }

        if ($this->emailNotifications->passwordResetRequested($user)) {
            $this->auditLogger->log($user, 'password_reset_requested', 'Auth', $user->id, 'Password reset link requested via self-service.');

            return $redirect->with([
                'reset_status' => 'sent',
                'reset_message' => 'A password reset link was sent to '.$this->maskEmail($user->email).'. Check your inbox and spam folder—the link expires in about one hour.',
            ]);
        }

        return $redirect->with([
            'reset_status' => 'send_failed',
            'reset_message' => 'We could not send the reset email right now. Try again in a few minutes or contact HR if the problem continues.',
        ]);
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return Str::mask($email, '*', 1);
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLength = strlen($local);
        $keep = min(2, $localLength);
        $maskedLocal = substr($local, 0, $keep).str_repeat('*', max(1, $localLength - $keep));

        return $maskedLocal.'@'.$domain;
    }
}
