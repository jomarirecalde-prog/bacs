@php
    $mail = $mailSettings ?? [];
    $mailer = old('mail_mailer', $mail['mailer'] ?? 'log');
    $smtpOpen = $mailer === 'smtp';
@endphp

<section id="settings-smtp" class="card card-accent-info scroll-mt-28 overflow-hidden" x-data="{ mailer: @js($mailer) }">
    <div class="card-header">
        <div class="min-w-0">
            <h2 class="card-title text-base">Email (SMTP)</h2>
            <p class="mt-0.5 text-sm text-muted">Outgoing mail for notifications, payslips, and system messages.</p>
        </div>
        @if (! empty($mail['using_database']))
            <span class="chip shrink-0">Saved in app</span>
        @else
            <span class="chip shrink-0">Using .env defaults</span>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.settings.smtp.update') }}" class="form-card-body">
        @csrf
        @method('PUT')

        <div class="alert-info text-xs sm:text-sm">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>
                Choose <strong class="font-semibold">Log only</strong> during development (messages go to <code class="text-xs">storage/logs</code>).
                Use <a href="{{ route('admin.email-notifications.preview') }}" class="font-semibold underline">Email notification preview</a> to send a test after saving.
            </span>
        </div>

        <div class="form-grid lg:grid-cols-3">
            <div>
                <label class="label" for="mail_mailer">Delivery</label>
                <select id="mail_mailer" name="mail_mailer" class="select @error('mail_mailer') input-error @enderror"
                        x-model="mailer" required>
                    <option value="smtp" @selected($mailer === 'smtp')>SMTP (recommended)</option>
                    <option value="log" @selected($mailer === 'log')>Log only (no delivery)</option>
                    <option value="sendmail" @selected($mailer === 'sendmail')>Sendmail</option>
                </select>
                @error('mail_mailer')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="mail_from_address">From address</label>
                <input id="mail_from_address" type="email" name="mail_from_address" class="input @error('mail_from_address') input-error @enderror"
                       value="{{ old('mail_from_address', $mail['from_address'] ?? '') }}" required autocomplete="off">
                @error('mail_from_address')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="mail_from_name">From name</label>
                <input id="mail_from_name" name="mail_from_name" class="input @error('mail_from_name') input-error @enderror"
                       value="{{ old('mail_from_name', $mail['from_name'] ?? '') }}" required autocomplete="off">
                @error('mail_from_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6 space-y-4 rounded-xl border border-line bg-canvas/60 p-4 sm:p-5" x-show="mailer === 'smtp'" x-cloak>
            <p class="text-sm font-semibold text-ink">SMTP server</p>
            <div class="form-grid lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <label class="label" for="mail_host">Host</label>
                    <input id="mail_host" name="mail_host" class="input @error('mail_host') input-error @enderror"
                           value="{{ old('mail_host', $mail['host'] ?? '') }}"
                           placeholder="smtp.example.com"
                           :required="mailer === 'smtp'">
                    @error('mail_host')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="mail_port">Port</label>
                    <input id="mail_port" type="number" name="mail_port" class="input @error('mail_port') input-error @enderror"
                           value="{{ old('mail_port', $mail['port'] ?? 587) }}" min="1" max="65535"
                           :required="mailer === 'smtp'">
                    @error('mail_port')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="mail_encryption">Encryption</label>
                    <select id="mail_encryption" name="mail_encryption" class="select @error('mail_encryption') input-error @enderror">
                        @php $enc = old('mail_encryption', $mail['encryption'] ?? 'tls'); @endphp
                        <option value="tls" @selected($enc === 'tls')>TLS (STARTTLS)</option>
                        <option value="ssl" @selected($enc === 'ssl')>SSL</option>
                        <option value="none" @selected($enc === 'none')>None</option>
                    </select>
                    @error('mail_encryption')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="mail_username">Username</label>
                    <input id="mail_username" name="mail_username" class="input @error('mail_username') input-error @enderror"
                           value="{{ old('mail_username', $mail['username'] ?? '') }}" autocomplete="off">
                    @error('mail_username')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="mail_password">Password</label>
                    <input id="mail_password" type="password" name="mail_password" class="input @error('mail_password') input-error @enderror"
                           autocomplete="new-password"
                           placeholder="{{ ! empty($mail['password_configured']) ? 'Leave blank to keep current password' : 'SMTP password' }}">
                    @error('mail_password')<p class="error-text">{{ $message }}</p>@enderror
                    @if (! empty($mail['password_configured']))
                        <p class="mt-1 text-xs text-muted">A password is already stored; enter a new one only to change it.</p>
                    @endif
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary mt-6 w-full sm:w-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Save SMTP settings
        </button>
    </form>
</section>
