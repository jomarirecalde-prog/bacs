<div
    id="sign-in"
    class="landing-auth-card landing-reveal landing-reveal-delay"
    x-data="{
        showPassword: false,
        submitting: false,
    }"
>
    <div class="mb-6 flex items-center gap-3">
        <x-bacs-logo class="h-11 w-auto" />
        <div class="min-w-0">
            <p class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-gold-400/90">Authorized access</p>
            <p class="text-sm font-semibold text-brand-100/90">{{ config('app.name') }}</p>
        </div>
    </div>

    <h2 class="text-2xl font-extrabold tracking-tight text-brand-50">Welcome Back</h2>
    <p class="mt-1 text-sm text-brand-200/75">Sign in to access your account.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-4">
        @csrf
        <div>
            <label class="landing-label" for="login">Username, employee ID, or email</label>
            <div class="landing-input-wrap">
                <svg class="landing-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <input
                    id="login"
                    class="landing-input @error('login') landing-input-error @enderror"
                    name="login"
                    value="{{ old('login') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Employee number, username, or email"
                >
            </div>
            @error('login')
                <p class="mt-2 text-xs text-critical-400" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <div class="mb-1.5 flex items-center justify-between gap-2">
                <label class="landing-label mb-0" for="password">Password</label>
                <button
                    type="button"
                    class="text-xs font-semibold text-brand-300 transition hover:text-brand-100"
                    @click="showPassword = !showPassword"
                    :aria-pressed="showPassword"
                >
                    <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                </button>
            </div>
            <div class="landing-input-wrap">
                <svg class="landing-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <input
                    id="password"
                    class="landing-input @error('password') landing-input-error @enderror"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                >
            </div>
            @error('password')
                <p class="mt-2 text-xs text-critical-400" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex cursor-pointer items-center gap-2 text-sm text-brand-100/85">
                <input type="checkbox" name="remember" class="landing-check"> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="landing-link text-xs">Forgot password?</a>
        </div>
        <button type="submit" class="btn-primary btn-block btn-lg" :disabled="submitting" :aria-busy="submitting" @click="submitting = true">
            <span x-show="!submitting">Sign In to Your Account</span>
            <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                Signing in…
            </span>
        </button>
    </form>

    <p class="mt-5 flex items-start justify-center gap-2 text-center text-xs text-brand-200/60 lg:justify-start lg:text-left">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        <span>Protected access for authorized users only. All sign-ins are audited.</span>
    </p>
    <p class="mt-3 text-center lg:text-left">
        <a class="landing-link-muted inline-flex items-center gap-1.5 text-xs" href="{{ route('station.login') }}">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Attendance station login
        </a>
    </p>
</div>
