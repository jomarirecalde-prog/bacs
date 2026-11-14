<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingController;
use App\Services\AuditLogger;
use App\Services\DataPrivacyConsentService;
use App\Models\User;
use App\Services\UserLoginLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DataPrivacyConsentService $consentService,
        private readonly UserLoginLookupService $loginLookup,
    ) {}

    public function show()
    {
        if (Auth::check()) {
            return redirect()->to($this->defaultDashboardUrl(Auth::user()));
        }

        return view('landing.index', app(LandingController::class)->viewData());
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(
            [
                'login' => ['required', 'string'],
                'password' => ['required', 'string'],
            ],
            [
                'login.required' => 'Enter your employee number, username, or email.',
                'password.required' => 'Enter your password.',
            ],
        );

        $key = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again shortly.',
            ]);
        }

        $user = $this->loginLookup->findByLogin($credentials['login']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Your account is not active. Please contact your administrator.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $loginUserId = $user->id;
        $loginUserName = $user->name;
        dispatch(function () use ($loginUserId, $loginUserName, $request): void {
            User::query()->whereKey($loginUserId)->update(['last_login_at' => now()]);
            $actor = User::query()->find($loginUserId);
            if ($actor) {
                app(AuditLogger::class)->log($actor, 'login', 'Auth', $loginUserId, "{$loginUserName} logged in.", $request);
            }
        })->afterResponse();

        if ($user->must_change_password) {
            return redirect()->route('profile.password')->with('error', 'Please change your temporary password before continuing.');
        }

        if (! $this->consentService->hasCurrentConsent($user)) {
            return redirect()->route('privacy.consent.show');
        }

        return $this->redirectAfterLogin($user);
    }

    private function defaultDashboardUrl(User $user): string
    {
        return $user->isManagement()
            ? route('admin.dashboard')
            : route('employee.dashboard');
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        return redirect()->intended($this->defaultDashboardUrl($user));
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->auditLogger->log($user, 'logout', 'Auth', $user->id, "{$user->name} logged out.");
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
