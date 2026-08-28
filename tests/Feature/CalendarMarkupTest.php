<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AttendanceEffect;
use App\Enums\CalendarEventType;
use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_grid_renders_a_complete_padded_week_matrix(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.calendar.index', ['view' => 'month', 'date' => '2026-08-15']))
            ->assertOk()
            ->getContent();

        // Sunday-first grid padded to whole weeks: August 2026 spans 6 rows.
        $this->assertSame(42, substr_count($html, 'class="cal-cell'));
        $this->assertStringContainsString('cal-weekhead', $html);
        $this->assertStringContainsString('cal-legend', $html);
        $this->assertStringContainsString('calendar-event-title', $html);
    }

    public function test_embedded_event_payload_is_valid_json(): void
    {
        $admin = User::factory()->admin()->create();

        CalendarEvent::query()->create([
            // Deliberately hostile title: quotes, angle brackets, and a script tag.
            'title' => 'Q3 "All-Hands" <script>alert(1)</script> & Review',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::NoAttendanceRequired,
            'description' => "Line one\nLine two with 'quotes'",
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'is_all_day' => true,
            'audience_type' => EventAudience::All,
            'status' => EventStatus::Published,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.calendar.index', ['view' => 'month', 'date' => '2026-08-15']))
            ->assertOk()
            ->getContent();

        // The raw script tag must never appear unescaped in the document.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        preg_match("/events: JSON\.parse\('(.*?)'\),/s", $html, $matches);
        $this->assertNotEmpty($matches, 'Could not locate the embedded event payload.');

        // Blade's @js emits JSON.parse('<json-encoded json string>'), so undo the
        // outer string encoding first — exactly what the browser does.
        $inner = json_decode('"'.$matches[1].'"');
        $this->assertIsString($inner, 'Embedded payload is not a decodable JS string literal.');

        $decoded = json_decode($inner, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);

        $event = reset($decoded);
        $this->assertSame('Q3 "All-Hands" <script>alert(1)</script> & Review', $event['title']);
        $this->assertTrue($event['non_working']);
        $this->assertTrue($event['multi_day']);
        $this->assertSame('HOLIDAY / NO ATTENDANCE REQUIRED', $event['banner']['label']);
    }

    public function test_multi_day_event_appears_on_every_covered_date(): void
    {
        $admin = User::factory()->admin()->create();

        CalendarEvent::query()->create([
            'title' => 'Company Outing',
            'event_type' => CalendarEventType::CompanyEvent,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'is_all_day' => true,
            'audience_type' => EventAudience::All,
            'status' => EventStatus::Published,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.calendar.index', ['view' => 'month', 'date' => '2026-08-15']))
            ->assertOk()
            ->getContent();

        // One chip per covered day (10th, 11th, 12th).
        $this->assertSame(3, substr_count($html, 'class="cal-chip cal-chip-gold'));

        // The day view for a middle date still shows it.
        $this->actingAs($admin)
            ->get(route('admin.calendar.index', ['view' => 'day', 'date' => '2026-08-11']))
            ->assertOk()
            ->assertSee('Company Outing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => AccountStatus::Active,
        ]);
    }
}
