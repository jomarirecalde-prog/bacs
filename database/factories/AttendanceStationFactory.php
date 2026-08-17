<?php

namespace Database\Factories;

use App\Enums\StationDeviceStatus;
use App\Enums\StationStatus;
use App\Models\AttendanceStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceStation>
 */
class AttendanceStationFactory extends Factory
{
    protected $model = AttendanceStation::class;

    public function definition(): array
    {
        return [
            'station_code' => 'BACS-STATION-'.fake()->unique()->numerify('###'),
            'station_name' => fake()->company().' Attendance Station',
            'password' => 'station-pass',
            'location' => fake()->streetAddress(),
            'description' => fake()->optional()->sentence(),
            'status' => StationStatus::Active,
            'device_status' => StationDeviceStatus::Unbound,
            'idle_timeout_minutes' => 0,
            'failed_login_attempts' => 0,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => StationStatus::Locked]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StationStatus::Inactive]);
    }
}
