@extends('layouts.app')

@section('title', 'Email Notifications')
@section('page-title', 'Email notification preview')
@section('page-subtitle', 'Preview HTML templates and send test messages (admin only)')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap gap-2">
            @foreach ($templates as $key => $label)
                <a href="{{ route('admin.email-notifications.preview', ['template' => $key]) }}"
                   class="{{ $active === $key ? 'tab-active' : 'tab-link' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="mt-6 overflow-hidden rounded-xl border border-line bg-canvas">
            <iframe src="{{ $previewUrl }}" title="Email preview" class="h-[720px] w-full bg-white"></iframe>
        </div>

        <form method="post" action="{{ route('admin.email-notifications.test') }}" class="mt-6 flex flex-wrap items-end gap-4">
            @csrf
            <input type="hidden" name="template" value="{{ $active }}">
            <div class="min-w-[16rem] flex-1">
                <label class="label" for="email">Send test email to</label>
                <input id="email" type="email" name="email" class="input" value="{{ auth()->user()->email }}" required>
            </div>
            <button type="submit" class="btn-primary">Send test email</button>
        </form>

        <p class="mt-4 text-sm text-muted">
            Configure SMTP in your <code class="text-xs">.env</code> file. With <code class="text-xs">MAIL_MAILER=log</code>, messages are written to the Laravel log instead of being delivered.
        </p>
    </div>
@endsection
