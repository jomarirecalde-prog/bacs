<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned payroll deduction rules keyed by effective_from (YYYY-MM-DD).
 * Payroll compute uses rules where effective_from <= period end date (latest wins).
 */
final class PayrollDeductionConfig
{
    private const REVISIONS_KEY = 'payroll_deduction_config_revisions';

    /**
     * @return array{
     *     statutory: array{
     *         auto_sss: bool,
     *         auto_philhealth: bool,
     *         auto_hdmf: bool,
     *         auto_tax: bool,
     *         philhealth_rate: float,
     *         hdmf_amount: float,
     *         sss_from_brackets: bool,
     *         tax_from_brackets: bool,
     *     },
     *     late: array{
     *         enabled: bool,
     *         calculation_mode: string,
     *         fixed_per_minute: float,
     *         block_minutes: int,
     *         amount_per_block: float,
     *         minimum_billable_minutes: int,
     *         round_minutes: string,
     *         minute_rate_from: string,
     *     },
     *     undertime: array{
     *         enabled: bool,
     *         calculation_mode: string,
     *         fixed_per_minute: float,
     *         block_minutes: int,
     *         amount_per_block: float,
     *         minimum_billable_minutes: int,
     *         round_minutes: string,
     *         minute_rate_from: string,
     *     },
     *     absence: array{enabled: bool},
     * }
     */
    public static function forDate(string $asOfDate): array
    {
        self::ensureBootstrapRevision();

        $revisions = self::rawRevisions();
        $chosen = null;

        foreach ($revisions as $revision) {
            $from = (string) ($revision['effective_from'] ?? '');
            if ($from === '' || $from > $asOfDate) {
                continue;
            }
            if ($chosen === null || $from > (string) $chosen['effective_from']) {
                $chosen = $revision;
            }
        }

        $config = is_array($chosen['config'] ?? null) ? $chosen['config'] : self::defaultConfig();

        return self::normalizeConfig($config);
    }

    /**
     * @param  array<string, mixed>  $partial  statutory|late|undertime|absence slices
     * @return list<array{effective_from: string, config: array<string, mixed>, saved_at: string, saved_by: ?int}>
     */
    public static function appendRevision(string $effectiveFrom, array $partial, ?int $savedBy = null): void
    {
        self::ensureBootstrapRevision();

        $base = self::forDate($effectiveFrom);
        foreach ($partial as $section => $values) {
            if (! is_array($values)) {
                continue;
            }
            $base[$section] = array_merge($base[$section] ?? [], $values);
        }

        $revisions = self::rawRevisions();
        $revisions = array_values(array_filter(
            $revisions,
            fn ($row) => (string) ($row['effective_from'] ?? '') !== $effectiveFrom
        ));

        $revisions[] = [
            'effective_from' => $effectiveFrom,
            'config' => self::normalizeConfig($base),
            'saved_at' => now()->toIso8601String(),
            'saved_by' => $savedBy,
        ];

        usort($revisions, fn ($a, $b) => strcmp((string) $a['effective_from'], (string) $b['effective_from']));

        Setting::put(self::REVISIONS_KEY, json_encode($revisions, JSON_THROW_ON_ERROR));
        Cache::forget('app_settings');
    }

    /**
     * @return list<array{effective_from: string, config: array<string, mixed>, saved_at: string, saved_by: ?int}>
     */
    public static function revisionHistory(): array
    {
        self::ensureBootstrapRevision();

        return self::rawRevisions();
    }

    public static function latestRevision(): ?array
    {
        $rows = self::revisionHistory();
        if ($rows === []) {
            return null;
        }

        return $rows[array_key_last($rows)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return self::normalizeConfig([
            'statutory' => [
                'auto_sss' => PayrollSettings::sssFromBracketTable(),
                'auto_philhealth' => PayrollSettings::philhealthRate() > 0,
                'auto_hdmf' => PayrollSettings::hdmfAmount() > 0,
                'auto_tax' => PayrollSettings::taxFromBracketTable(),
                'philhealth_rate' => PayrollSettings::philhealthRate(),
                'hdmf_amount' => PayrollSettings::hdmfAmount(),
                'sss_from_brackets' => PayrollSettings::sssFromBracketTable(),
                'tax_from_brackets' => PayrollSettings::taxFromBracketTable(),
            ],
            'late' => [
                'enabled' => true,
                'calculation_mode' => 'derived_minute_rate',
                'fixed_per_minute' => 0.0,
                'block_minutes' => 15,
                'amount_per_block' => 0.0,
                'minimum_billable_minutes' => 0,
                'round_minutes' => 'none',
                'minute_rate_from' => 'hourly',
            ],
            'undertime' => [
                'enabled' => true,
                'calculation_mode' => 'derived_minute_rate',
                'fixed_per_minute' => 0.0,
                'block_minutes' => 15,
                'amount_per_block' => 0.0,
                'minimum_billable_minutes' => 0,
                'round_minutes' => 'none',
                'minute_rate_from' => 'hourly',
            ],
            'absence' => [
                'enabled' => true,
            ],
        ]);
    }

    private static function ensureBootstrapRevision(): void
    {
        if (self::rawRevisions() !== []) {
            return;
        }

        Setting::put(self::REVISIONS_KEY, json_encode([
            [
                'effective_from' => '1970-01-01',
                'config' => self::defaultConfig(),
                'saved_at' => now()->toIso8601String(),
                'saved_by' => null,
            ],
        ], JSON_THROW_ON_ERROR));
        Cache::forget('app_settings');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rawRevisions(): array
    {
        $raw = Setting::get(self::REVISIONS_KEY);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeConfig(array $config): array
    {
        $defaults = [
            'statutory' => [
                'auto_sss' => false,
                'auto_philhealth' => false,
                'auto_hdmf' => false,
                'auto_tax' => false,
                'philhealth_rate' => 0.0,
                'hdmf_amount' => 0.0,
                'sss_from_brackets' => false,
                'tax_from_brackets' => false,
            ],
            'late' => [
                'enabled' => true,
                'calculation_mode' => 'derived_minute_rate',
                'fixed_per_minute' => 0.0,
                'block_minutes' => 15,
                'amount_per_block' => 0.0,
                'minimum_billable_minutes' => 0,
                'round_minutes' => 'none',
                'minute_rate_from' => 'hourly',
            ],
            'undertime' => [
                'enabled' => true,
                'calculation_mode' => 'derived_minute_rate',
                'fixed_per_minute' => 0.0,
                'block_minutes' => 15,
                'amount_per_block' => 0.0,
                'minimum_billable_minutes' => 0,
                'round_minutes' => 'none',
                'minute_rate_from' => 'hourly',
            ],
            'absence' => [
                'enabled' => true,
            ],
        ];

        $merged = [];
        foreach ($defaults as $section => $sectionDefaults) {
            $incoming = is_array($config[$section] ?? null) ? $config[$section] : [];
            $merged[$section] = array_merge($sectionDefaults, $incoming);
        }

        foreach (['statutory'] as $section) {
            $merged[$section]['auto_sss'] = filter_var($merged[$section]['auto_sss'], FILTER_VALIDATE_BOOL);
            $merged[$section]['auto_philhealth'] = filter_var($merged[$section]['auto_philhealth'], FILTER_VALIDATE_BOOL);
            $merged[$section]['auto_hdmf'] = filter_var($merged[$section]['auto_hdmf'], FILTER_VALIDATE_BOOL);
            $merged[$section]['auto_tax'] = filter_var($merged[$section]['auto_tax'], FILTER_VALIDATE_BOOL);
            $merged[$section]['sss_from_brackets'] = filter_var($merged[$section]['sss_from_brackets'], FILTER_VALIDATE_BOOL);
            $merged[$section]['tax_from_brackets'] = filter_var($merged[$section]['tax_from_brackets'], FILTER_VALIDATE_BOOL);
            $merged[$section]['philhealth_rate'] = max(0, (float) $merged[$section]['philhealth_rate']);
            $merged[$section]['hdmf_amount'] = max(0, (float) $merged[$section]['hdmf_amount']);
        }

        foreach (['late', 'undertime'] as $section) {
            $merged[$section]['enabled'] = filter_var($merged[$section]['enabled'], FILTER_VALIDATE_BOOL);
            $merged[$section]['fixed_per_minute'] = max(0, (float) $merged[$section]['fixed_per_minute']);
            $merged[$section]['block_minutes'] = max(1, (int) $merged[$section]['block_minutes']);
            $merged[$section]['amount_per_block'] = max(0, (float) $merged[$section]['amount_per_block']);
            $merged[$section]['minimum_billable_minutes'] = max(0, (int) $merged[$section]['minimum_billable_minutes']);
            $mode = (string) $merged[$section]['calculation_mode'];
            if (! in_array($mode, ['derived_minute_rate', 'fixed_per_minute', 'fixed_per_block'], true)) {
                $mode = 'derived_minute_rate';
            }
            $merged[$section]['calculation_mode'] = $mode;
            $round = (string) $merged[$section]['round_minutes'];
            if (! in_array($round, ['none', 'ceil', 'floor', 'nearest'], true)) {
                $round = 'none';
            }
            $merged[$section]['round_minutes'] = $round;
            $from = (string) $merged[$section]['minute_rate_from'];
            if (! in_array($from, ['hourly', 'daily'], true)) {
                $from = 'hourly';
            }
            $merged[$section]['minute_rate_from'] = $from;
        }

        $merged['absence']['enabled'] = filter_var($merged['absence']['enabled'], FILTER_VALIDATE_BOOL);

        return $merged;
    }
}
