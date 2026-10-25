<?php

namespace App\Services\Payroll;

use App\Enums\PayrollWorkdayClassification;
use App\Models\PayrollOvertimeCompensationRate;
use App\Models\User;
use App\Support\OvertimeMultiplierNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollOvertimeCompensationRateService
{
    /**
     * @return array{inserted: int, skipped: int}
     */
    public function loadDefaults(string $effectiveFrom, ?User $actor = null): array
    {
        $inserted = 0;
        $skipped = 0;
        $now = now();

        foreach (PayrollOvertimeCompensationDefaults::rows() as $row) {
            if ($row['condition_code'] && PayrollOvertimeCompensationRate::query()->where('condition_code', $row['condition_code'])->exists()) {
                $skipped++;

                continue;
            }

            PayrollOvertimeCompensationRate::query()->create([
                'condition_code' => $row['condition_code'],
                'label' => $row['label'],
                'workday_classification' => $row['workday_classification']->value,
                'is_rest_day' => $row['is_rest_day'],
                'is_night_shift' => $row['is_night_shift'],
                'is_overtime' => $row['is_overtime'],
                'multiplier' => $row['multiplier'],
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'remarks' => 'Loaded from default OT compensation reference.',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): PayrollOvertimeCompensationRate
    {
        $normalized = OvertimeMultiplierNormalizer::normalize($data['multiplier_input'] ?? $data['multiplier'] ?? 1);

        $payload = [
            'condition_code' => $data['condition_code'] ?? null,
            'label' => $data['label'],
            'workday_classification' => $data['workday_classification'],
            'is_rest_day' => (bool) ($data['is_rest_day'] ?? false),
            'is_night_shift' => (bool) ($data['is_night_shift'] ?? false),
            'is_overtime' => (bool) ($data['is_overtime'] ?? true),
            'multiplier' => $normalized['multiplier'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'remarks' => $data['remarks'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ];

        $this->assertNoConflictingActiveRate($payload);

        return PayrollOvertimeCompensationRate::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PayrollOvertimeCompensationRate $rate, array $data, User $actor): PayrollOvertimeCompensationRate
    {
        $previous = $rate->only([
            'multiplier', 'effective_from', 'effective_to', 'is_active', 'remarks',
        ]);

        if (isset($data['multiplier_input']) || isset($data['multiplier'])) {
            $normalized = OvertimeMultiplierNormalizer::normalize($data['multiplier_input'] ?? $data['multiplier']);
            $data['multiplier'] = $normalized['multiplier'];
        }

        $payload = array_filter([
            'label' => $data['label'] ?? null,
            'workday_classification' => $data['workday_classification'] ?? null,
            'is_rest_day' => array_key_exists('is_rest_day', $data) ? (bool) $data['is_rest_day'] : null,
            'is_night_shift' => array_key_exists('is_night_shift', $data) ? (bool) $data['is_night_shift'] : null,
            'is_overtime' => array_key_exists('is_overtime', $data) ? (bool) $data['is_overtime'] : null,
            'multiplier' => $data['multiplier'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            'remarks' => $data['remarks'] ?? null,
            'updated_by' => $actor->id,
        ], fn ($value) => $value !== null);

        $merged = array_merge($rate->only([
            'workday_classification', 'is_rest_day', 'is_night_shift', 'is_overtime', 'effective_from', 'is_active',
        ]), $payload);

        $this->assertNoConflictingActiveRate($merged, $rate->id);

        $rate->update($payload);

        return $rate->fresh();
    }

    public function resolveRate(
        PayrollWorkdayClassification $classification,
        bool $isRestDay,
        bool $isNightShift,
        string $workDate,
    ): ?PayrollOvertimeCompensationRate {
        return PayrollOvertimeCompensationRate::query()
            ->active()
            ->effectiveOn($workDate)
            ->where('workday_classification', $classification->value)
            ->where('is_rest_day', $isRestDay)
            ->where('is_night_shift', $isNightShift)
            ->where('is_overtime', true)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoConflictingActiveRate(array $payload, ?int $ignoreId = null): void
    {
        if (! ($payload['is_active'] ?? true)) {
            return;
        }

        $classification = $payload['workday_classification'] instanceof PayrollWorkdayClassification
            ? $payload['workday_classification']->value
            : (string) $payload['workday_classification'];

        $query = PayrollOvertimeCompensationRate::query()
            ->active()
            ->where('workday_classification', $classification)
            ->where('is_rest_day', (bool) ($payload['is_rest_day'] ?? false))
            ->where('is_night_shift', (bool) ($payload['is_night_shift'] ?? false))
            ->where('is_overtime', (bool) ($payload['is_overtime'] ?? true))
            ->where('effective_from', $payload['effective_from']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'An active rate already exists for this condition and effective date.',
            ]);
        }
    }
}
