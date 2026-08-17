@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden lg:flex flex-col justify-between bg-gradient-to-br from-slate-950 via-slate-900 to-brand-900 p-12 text-white">
        <div>
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-600 font-extrabold">D</div>
                <div>
                    <div class="font-bold">BACS DTR</div>
                    <div class="text-sm text-slate-300">Daily Time Record Monitoring</div>
                </div>
            </div>
        </div>
        <div>
            <h1 class="text-4xl font-extrabold leading-tight">Clean attendance.<br>Accurate DTR.</h1>
            <p class="mt-4 max-w-md text-slate-300">Employees clock in and out in Philippine Standard Time. Supervisors monitor attendance, correct records, and generate official DTR reports.</p>
        </div>
        <p class="text-xs text-slate-500">Asia/Manila · Server-authoritative timestamps</p>
    </div>
    <div class="flex items-center justify-center p-6 sm:p-12 bg-slate-50">
        <div class="w-full max-w-md">
            <div class="mb-8 lg:hidden">
                <div class="font-extrabold text-xl">BACS DTR</div>
                <div class="text-sm text-slate-500">Sign in to continue</div>
            </div>
            <div class="card p-8">
                <h2 class="text-2xl font-extrabold text-slate-900">Welcome back</h2>
                <p class="mt-1 text-sm text-slate-500">Use your employee number, username, or email.</p>
                <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="label">Username, employee number, or email</label>
                        <input class="input" name="login" value="{{ old('login') }}" required autofocus>
                    </div>
                    <div>
                        <label class="label">Password</label>
                        <input class="input" type="password" name="password" required>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300"> Remember me
                    </label>
                    @error('login')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button class="btn-primary w-full">Sign in</button>
                </form>
            </div>
            <p class="mt-6 text-center text-xs text-slate-500">Authorized personnel only. All sign-ins are audited.</p>
            <p class="mt-2 text-center text-xs"><a class="font-semibold text-brand-700" href="{{ url('/attendance-station/login') }}">Attendance Station login</a></p>
        </div>
    </div>
</div>
@endsection
