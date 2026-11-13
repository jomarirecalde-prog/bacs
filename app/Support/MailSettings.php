<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

final class MailSettings
{
    public const KEYS = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    public static function applyToConfig(): void
    {
        if (! static::isConfiguredInDatabase()) {
            return;
        }

        $mailer = static::mailer();
        config(['mail.default' => $mailer]);

        if ($mailer === 'smtp') {
            config([
                'mail.mailers.smtp.host' => static::host(),
                'mail.mailers.smtp.port' => static::port(),
                'mail.mailers.smtp.username' => static::username(),
                'mail.mailers.smtp.password' => static::password() ?? '',
                'mail.mailers.smtp.encryption' => static::encryption(),
            ]);
        }

        config([
            'mail.from.address' => static::fromAddress(),
            'mail.from.name' => static::fromName(),
        ]);
    }

    public static function isConfiguredInDatabase(): bool
    {
        foreach (self::KEYS as $key) {
            if (Setting::has($key)) {
                return true;
            }
        }

        return false;
    }

    public static function formState(): array
    {
        return [
            'mailer' => static::mailer(),
            'host' => static::host(),
            'port' => static::port(),
            'username' => static::username() ?? '',
            'encryption' => static::encryptionFormValue(),
            'from_address' => static::fromAddress(),
            'from_name' => static::fromName(),
            'password_configured' => static::hasStoredPassword(),
            'using_database' => static::isConfiguredInDatabase(),
        ];
    }

    public static function mailer(): string
    {
        return (string) static::resolved('mail_mailer', fn () => (string) config('mail.default', env('MAIL_MAILER', 'log')));
    }

    public static function host(): string
    {
        return (string) static::resolved('mail_host', fn () => (string) env('MAIL_HOST', '127.0.0.1'));
    }

    public static function port(): int
    {
        return (int) static::resolved('mail_port', fn () => (int) env('MAIL_PORT', 2525));
    }

    public static function username(): ?string
    {
        $value = static::resolved('mail_username', fn () => env('MAIL_USERNAME'));

        return $value === null || $value === '' ? null : (string) $value;
    }

    public static function password(): ?string
    {
        if (Setting::has('mail_password')) {
            $encrypted = Setting::get('mail_password');

            if ($encrypted === null || $encrypted === '') {
                return null;
            }

            try {
                return Crypt::decryptString((string) $encrypted);
            } catch (\Throwable) {
                return null;
            }
        }

        $env = env('MAIL_PASSWORD');

        return $env !== null && $env !== '' ? (string) $env : null;
    }

    public static function hasStoredPassword(): bool
    {
        if (! Setting::has('mail_password')) {
            return env('MAIL_PASSWORD') !== null && env('MAIL_PASSWORD') !== '';
        }

        $encrypted = Setting::get('mail_password');

        return $encrypted !== null && $encrypted !== '';
    }

    public static function encryption(): ?string
    {
        $value = static::encryptionFormValue();

        if ($value === 'none') {
            return null;
        }

        return $value;
    }

    public static function encryptionFormValue(): string
    {
        if (Setting::has('mail_encryption')) {
            $value = (string) Setting::get('mail_encryption');

            return $value === '' ? 'none' : $value;
        }

        $env = env('MAIL_ENCRYPTION', 'tls');

        if ($env === null || $env === '') {
            return 'none';
        }

        return (string) $env;
    }

    public static function fromAddress(): string
    {
        return (string) static::resolved('mail_from_address', fn () => (string) config('mail.from.address', env('MAIL_FROM_ADDRESS', 'hello@example.com')));
    }

    public static function fromName(): string
    {
        return (string) static::resolved('mail_from_name', fn () => (string) config('mail.from.name', env('MAIL_FROM_NAME', env('APP_NAME', 'BACS'))));
    }

    /**
     * @param  callable(): mixed  $envFallback
     */
    private static function resolved(string $key, callable $envFallback): mixed
    {
        if (Setting::has($key)) {
            return Setting::get($key);
        }

        return $envFallback();
    }
}
