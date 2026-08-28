<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight signal for admin attendance dashboards to refresh without
 * polling. Contains no employee PII — only ids and punch metadata.
 */
class AttendanceRecorded implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $date,
        public int $employeeId,
        public string $code,
        public ?int $attendanceId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('attendance.dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'attendance.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'date' => $this->date,
            'employee_id' => $this->employeeId,
            'code' => $this->code,
            'attendance_id' => $this->attendanceId,
        ];
    }
}
