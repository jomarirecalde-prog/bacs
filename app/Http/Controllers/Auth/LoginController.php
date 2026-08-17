<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again shortly.',
            ]);
        }

        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempt = Auth::attempt(
            [$field => $credentials['login'], 'password' => $credentials['password']],
            $request->boolean('remember')
        );

        if (! $attempt) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        $user = $request->user();

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Your account is not active. Please contact your administrator.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        $this->auditLogger->log($user, 'login', 'Auth', $user->id, "{$user->name} logged in.");

        if ($user->must_change_password) {
            return redirect()->route('profile.password')->with('error', 'Please change your temporary password before continuing.');
        }

        return redirect()->intended(route('home'));
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
