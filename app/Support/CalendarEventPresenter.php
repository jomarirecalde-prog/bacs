<?php

namespace App\Support;

use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;

/**
 * Shapes an event for the client-side details modal.
 *
 * Both the admin and employee calendars render from this payload, so the two
 * surfaces can never drift. Management URLs are only ever included when the
 * viewer is allowed to manage events; the employee payload simply does not
 * contain them.
 */
class CalendarEventPresenter
{
    /**
     * @param  bool  $includeInternal  Expose audience and authorship (management only).
     * @param  bool  $canManage  Expose edit/delete targets (Super Admin only).
     * @return array<string, mixed>
     */
    public static function forModal(CalendarEvent $event, bool $includeInternal = false, bool $canManage = false): array
    {
        $type = $event->event_type;

        $payload = [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $type->label(),
            'type_short' => $type->shortLabel(),
            'tone' => $type->tone(),
            'icon' => $type->iconPath(),
            'date' => $event->dateLabel(),
            'time' => $event->timeLabel(),
            'all_day' => $event->is_all_day,
            'multi_day' => $event->isMultiDay(),
            'description' => $event->description,
            'location' => $event->location,
            'instructions' => $event->additional_instructions,
            'status' => $event->status->label(),
            'status_tone' => $event->status->tone(),
            'non_working' => $event->isNonWorking(),
            'effect' => $event->attendance_effect?->label(),
            'supports_effect' => $type->supportsAttendanceEffect(),
            'banner' => self::banner($event),
        ];

        if ($includeInternal) {
            $payload['audience'] = $event->audienceLabel();
            $payload['created_by'] = $event->creator?->name;
            $payload['created_at'] = ManilaTime::formatDate($event->created_at);
            $payload['affects_attendance'] = $event->affectsAttendance();
            $payload['show_url'] = route('admin.calendar.events.show', $event);
        }

        if ($canManage) {
            $payload['edit_url'] = route('admin.calendar.events.edit', $event);
            $payload['delete_url'] = route('admin.calendar.events.destroy', $event);
        }

        return $payload;
    }

    /**
     * The prominent, plain-language classification shown at the top of the modal.
     *
     * @return array{label: string, tone: string}|null
     */
    private static function banner(CalendarEvent $event): ?array
    {
        if ($event->isNonWorking()) {
            return [
                'label' => 'HOLIDAY / NO ATTENDANCE REQUIRED',
                'tone' => 'brand',
            ];
        }

        return match ($event->event_type) {
            CalendarEventType::Meeting => ['label' => 'COMPANY MEETING', 'tone' => 'info'],
            CalendarEventType::Announcement => ['label' => 'ANNOUNCEMENT', 'tone' => 'warn'],
            CalendarEventType::CompanyEvent => ['label' => 'COMPANY EVENT', 'tone' => 'gold'],
            CalendarEventType::Holiday, CalendarEventType::SpecialNonWorking => [
                'label' => strtoupper($event->event_type->label()).' / INFORMATIONAL',
                'tone' => 'neutral',
            ],
            default => null,
        };
    }
}
