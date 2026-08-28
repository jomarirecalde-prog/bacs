<?php

namespace Tests\Feature;

use App\Broadcasting\UserChannel;
use App\Enums\AccountStatus;
use App\Enums\AttendanceEffect;
use App\Enums\AttendanceStatus;
use App\Enums\CalendarEventType;
use App\Enums\EmploymentStatus;
use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Events\CalendarEventChanged;
use App\Events\NotificationReceived;
use App\Models\AppNotification;
use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceCalculator;
use App\Services\HolidayResolver;
use App\Services\NotificationService;
use App\Support\CalendarEventPresenter;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CalendarModuleTest extends TestCase
{
    use RefreshDatabase;

    private WorkSchedule $schedule;

    private Department $itDept;

    private Department $hrDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => AccountStatus::Active,
        ]);

        $this->itDept = Department::query()->create(['name' => 'IT', 'status' => AccountStatus::Active]);
        $this->hrDept = Department::query()->create(['name' => 'HR', 'status' => AccountStatus::Active]);
    }

    /* -----------------------------------------------------------------
     | Permissions
     |-----------------------------------------------------------------*/

    public function test_employee_cannot_reach_any_calendar_management_route(): void
    {
        $employee = $this->makeEmployee('staff', $this->itDept);
        $event = $this->makeEvent();

        $this->actingAs($employee->user)->get(route('admin.calendar.index'))->assertForbidden();
        $this->actingAs($employee->user)->get(route('admin.calendar.events.index'))->assertForbidden();
        $this->actingAs($employee->user)->get(route('admin.calendar.events.create'))->assertForbidden();
        $this->actingAs($employee->user)->get(route('admin.calendar.events.edit', $event))->assertForbidden();
        $this->actingAs($employee->user)->post(route('admin.calendar.events.store'), [])->assertForbidden();
        $this->actingAs($employee->user)->put(route('admin.calendar.events.update', $event), [])->assertForbidden();
        $this->actingAs($employee->user)->delete(route('admin.calendar.events.destroy', $event))->assertForbidden();
    }

    public function test_guest_cannot_reach_calendar_routes(): void
    {
        $this->get(route('admin.calendar.index'))->assertRedirect(route('login'));
        $this->get(route('employee.calendar'))->assertRedirect(route('login'));
    }

    public function test_supervisor_can_read_but_not_write_events(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $event = $this->makeEvent();

        $this->actingAs($supervisor)->get(route('admin.calendar.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.calendar.events.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.calendar.events.show', $event))->assertOk();

        $this->actingAs($supervisor)->get(route('admin.calendar.events.create'))->assertForbidden();
        $this->actingAs($supervisor)->post(route('admin.calendar.events.store'), [])->assertForbidden();
        $this->actingAs($supervisor)->delete(route('admin.calendar.events.destroy', $event))->assertForbidden();
    }

    public function test_presenter_withholds_management_data_by_default(): void
    {
        $event = $this->makeEvent();

        $employeePayload = CalendarEventPresenter::forModal($event);
        $this->assertArrayNotHasKey('edit_url', $employeePayload);
        $this->assertArrayNotHasKey('delete_url', $employeePayload);
        $this->assertArrayNotHasKey('show_url', $employeePayload);
        $this->assertArrayNotHasKey('audience', $employeePayload);
        $this->assertArrayNotHasKey('created_by', $employeePayload);
        // Read-only display data is still present.
        $this->assertSame($event->title, $employeePayload['title']);

        $supervisorPayload = CalendarEventPresenter::forModal($event, includeInternal: true);
        $this->assertArrayHasKey('audience', $supervisorPayload);
        $this->assertArrayNotHasKey('edit_url', $supervisorPayload);

        $adminPayload = CalendarEventPresenter::forModal($event, includeInternal: true, canManage: true);
        $this->assertArrayHasKey('edit_url', $adminPayload);
        $this->assertArrayHasKey('delete_url', $adminPayload);
    }

    public function test_employee_calendar_html_embeds_no_management_urls(): void
    {
        $employee = $this->makeEmployee('payload', $this->itDept);
        $this->makeEvent(['title' => 'Open Event', 'start_date' => '2026-08-20', 'end_date' => '2026-08-20']);

        $html = $this->actingAs($employee->user)
            ->get(route('employee.calendar', ['date' => '2026-08-20']))
            ->assertOk()
            ->getContent();

        // Assert against the JSON keys inside the x-data payload (Blade's @js
        // escapes quotes as \u0022); the Alpine template itself legitimately
        // mentions these names, so a plain substring search would false-positive.
        $this->assertStringNotContainsString('\u0022edit_url\u0022', $html);
        $this->assertStringNotContainsString('\u0022delete_url\u0022', $html);
        $this->assertStringNotContainsString('\u0022show_url\u0022', $html);
        $this->assertStringNotContainsString('\u0022created_by\u0022', $html);
        $this->assertStringNotContainsString('\u0022audience\u0022', $html);
    }

    public function test_supervisor_sees_details_but_no_edit_affordance(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->makeEvent(['title' => 'Reviewed Event', 'start_date' => '2026-08-20', 'end_date' => '2026-08-20']);

        $html = $this->actingAs($supervisor)
            ->get(route('admin.calendar.index', ['date' => '2026-08-20']))
            ->assertOk()
            ->assertSee('Reviewed Event')
            ->getContent();

        $this->assertStringContainsString('\u0022created_by\u0022', $html);
        $this->assertStringNotContainsString('\u0022edit_url\u0022', $html);
        $this->assertStringNotContainsString('\u0022delete_url\u0022', $html);
    }

    public function test_superadmin_can_create_edit_and_delete_events(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.calendar.events.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Monthly Company Meeting',
            'event_type' => CalendarEventType::Meeting->value,
            'description' => 'All hands.',
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => '0',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'location' => 'Main Conference Room',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        $event = CalendarEvent::query()->firstOrFail();
        $this->assertSame('Monthly Company Meeting', $event->title);
        $this->assertSame('Main Conference Room', $event->location);
        $this->assertFalse($event->is_all_day);
        $this->assertSame($admin->id, $event->created_by);

        $this->actingAs($admin)->put(route('admin.calendar.events.update', $event), [
            'title' => 'Quarterly Company Meeting',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        $this->assertSame('Quarterly Company Meeting', $event->fresh()->title);
        $this->assertTrue($event->fresh()->is_all_day);
        // Switching to all-day must clear the timed fields.
        $this->assertNull($event->fresh()->start_time);

        $this->actingAs($admin)->delete(route('admin.calendar.events.destroy', $event))->assertRedirect();
        $this->assertSoftDeleted('calendar_events', ['id' => $event->id]);
    }

    /* -----------------------------------------------------------------
     | Audience visibility
     |-----------------------------------------------------------------*/

    public function test_employee_only_sees_events_addressed_to_them(): void
    {
        $itEmployee = $this->makeEmployee('it-guy', $this->itDept);
        $hrEmployee = $this->makeEmployee('hr-guy', $this->hrDept);

        $forEveryone = $this->makeEvent(['title' => 'Company Anniversary']);

        $forIt = $this->makeEvent([
            'title' => 'IT Systems Downtime',
            'audience_type' => EventAudience::Departments,
        ]);
        $forIt->departments()->sync([$this->itDept->id]);

        $forHrPerson = $this->makeEvent([
            'title' => 'Private Coaching Session',
            'audience_type' => EventAudience::Employees,
        ]);
        $forHrPerson->employees()->sync([$hrEmployee->id]);

        $visibleToIt = CalendarEvent::query()->visibleToEmployee($itEmployee)->pluck('title')->all();
        $this->assertContains('Company Anniversary', $visibleToIt);
        $this->assertContains('IT Systems Downtime', $visibleToIt);
        $this->assertNotContains('Private Coaching Session', $visibleToIt);

        $visibleToHr = CalendarEvent::query()->visibleToEmployee($hrEmployee)->pluck('title')->all();
        $this->assertContains('Company Anniversary', $visibleToHr);
        $this->assertNotContains('IT Systems Downtime', $visibleToHr);
        $this->assertContains('Private Coaching Session', $visibleToHr);
    }

    public function test_restricted_event_is_not_rendered_on_another_employees_calendar(): void
    {
        $itEmployee = $this->makeEmployee('viewer', $this->itDept);
        $hrEmployee = $this->makeEmployee('target', $this->hrDept);

        $secret = $this->makeEvent([
            'title' => 'Confidential Performance Review',
            'audience_type' => EventAudience::Employees,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
        ]);
        $secret->employees()->sync([$hrEmployee->id]);

        $this->actingAs($itEmployee->user)
            ->get(route('employee.calendar', ['date' => '2026-08-20']))
            ->assertOk()
            ->assertDontSee('Confidential Performance Review');

        $this->actingAs($hrEmployee->user)
            ->get(route('employee.calendar', ['date' => '2026-08-20']))
            ->assertOk()
            ->assertSee('Confidential Performance Review');
    }

    public function test_draft_events_are_hidden_from_employees(): void
    {
        $employee = $this->makeEmployee('drafts', $this->itDept);

        $this->makeEvent([
            'title' => 'Unannounced Team Building',
            'status' => EventStatus::Draft,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
        ]);

        $this->actingAs($employee->user)
            ->get(route('employee.calendar', ['date' => '2026-08-20']))
            ->assertOk()
            ->assertDontSee('Unannounced Team Building');
    }

    public function test_cancelled_event_stays_visible_but_never_affects_attendance(): void
    {
        $employee = $this->makeEmployee('cancelled', $this->itDept);

        $this->makeEvent([
            'title' => 'Called Off Meeting',
            'event_type' => CalendarEventType::Meeting,
            'status' => EventStatus::Cancelled,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
        ]);

        // Employees should see that a meeting was called off, not have it vanish.
        $this->actingAs($employee->user)
            ->get(route('employee.calendar', ['date' => '2026-08-20']))
            ->assertOk()
            ->assertSee('Called Off Meeting')
            ->assertSee('Cancelled');

        $this->makeEvent([
            'title' => 'Called Off Holiday',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::CompanyHoliday,
            'status' => EventStatus::Cancelled,
            'start_date' => '2026-08-31',
            'end_date' => '2026-08-31',
        ]);

        $this->assertFalse(app(HolidayResolver::class)->isNonWorking('2026-08-31'));
    }

    /* -----------------------------------------------------------------
     | Holiday / attendance integration
     |-----------------------------------------------------------------*/

    public function test_holiday_event_prevents_employee_from_being_marked_absent(): void
    {
        $employee = $this->makeEmployee('heroes', $this->itDept);

        // 2026-08-31 is a Monday, i.e. a normal working day.
        $date = '2026-08-31';
        $this->assertSame(1, ManilaTime::parse($date)->isoWeekday());

        $calculator = app(AttendanceCalculator::class);

        $before = $calculator->calculate($date, null, null, $this->schedule, $employee->id);
        $this->assertSame(AttendanceStatus::Absent, $before['status']);

        $this->makeEvent([
            'title' => 'National Heroes Day',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::NoAttendanceRequired,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $after = app(AttendanceCalculator::class)
            ->calculate($date, null, null, $this->schedule, $employee->id);

        $this->assertSame(AttendanceStatus::Holiday, $after['status']);
    }

    public function test_multi_day_holiday_covers_its_whole_range(): void
    {
        $this->makeEvent([
            'title' => 'Holy Week Break',
            'event_type' => CalendarEventType::SpecialNonWorking,
            'attendance_effect' => AttendanceEffect::SpecialNonWorking,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-03',
        ]);

        $resolver = app(HolidayResolver::class);

        $this->assertFalse($resolver->isNonWorking('2026-03-31'));
        $this->assertTrue($resolver->isNonWorking('2026-04-01'));
        $this->assertTrue($resolver->isNonWorking('2026-04-02'));
        $this->assertTrue($resolver->isNonWorking('2026-04-03'));
        $this->assertFalse($resolver->isNonWorking('2026-04-04'));

        $this->assertSame('Holy Week Break', $resolver->nameForDate('2026-04-02'));
    }

    public function test_informational_holiday_does_not_change_attendance(): void
    {
        $employee = $this->makeEmployee('info-only', $this->itDept);
        $date = '2026-08-31';

        $this->makeEvent([
            'title' => 'Founders Day Notice',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::Informational,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $result = app(AttendanceCalculator::class)
            ->calculate($date, null, null, $this->schedule, $employee->id);

        $this->assertSame(AttendanceStatus::Absent, $result['status']);
        $this->assertFalse(app(HolidayResolver::class)->isNonWorking($date));
    }

    public function test_draft_holiday_does_not_affect_attendance(): void
    {
        $date = '2026-08-31';

        $this->makeEvent([
            'title' => 'Proposed Holiday',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::CompanyHoliday,
            'status' => EventStatus::Draft,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $this->assertFalse(app(HolidayResolver::class)->isNonWorking($date));
    }

    public function test_legacy_settings_holidays_still_resolve(): void
    {
        Holiday::query()->create([
            'name' => 'Rizal Day',
            'holiday_date' => '2026-12-30',
            'type' => 'regular',
        ]);

        $resolver = app(HolidayResolver::class);
        $this->assertTrue($resolver->isNonWorking('2026-12-30'));
        $this->assertSame('Rizal Day', $resolver->nameForDate('2026-12-30'));
    }

    public function test_deleting_a_holiday_event_restores_normal_attendance_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('recalc', $this->itDept);
        $date = '2026-08-31';

        $event = $this->makeEvent([
            'title' => 'Cancelled Holiday',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::CompanyHoliday,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $this->assertTrue(app(HolidayResolver::class)->isNonWorking($date));

        $this->actingAs($admin)
            ->delete(route('admin.calendar.events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $result = app(AttendanceCalculator::class)
            ->calculate($date, null, null, $this->schedule, $employee->id);

        $this->assertSame(AttendanceStatus::Absent, $result['status']);
    }

    public function test_department_holiday_does_not_affect_employees_outside_the_audience(): void
    {
        $itEmployee = $this->makeEmployee('it-hol', $this->itDept);
        $hrEmployee = $this->makeEmployee('hr-hol', $this->hrDept);

        $event = $this->makeEvent([
            'title' => 'IT Shutdown Day',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::NoAttendanceRequired,
            'audience_type' => EventAudience::Departments,
            'start_date' => '2026-08-31',
            'end_date' => '2026-08-31',
        ]);
        $event->departments()->sync([$this->itDept->id]);
        app(HolidayResolver::class)->flush();

        $resolver = app(HolidayResolver::class);
        $this->assertTrue($resolver->isNonWorking('2026-08-31', $itEmployee));
        $this->assertFalse($resolver->isNonWorking('2026-08-31', $hrEmployee));

        $calculator = app(AttendanceCalculator::class);
        $this->assertSame(
            AttendanceStatus::Holiday,
            $calculator->calculate('2026-08-31', null, null, $this->schedule, $itEmployee->id)['status']
        );
        $this->assertSame(
            AttendanceStatus::Absent,
            $calculator->calculate('2026-08-31', null, null, $this->schedule, $hrEmployee->id)['status']
        );

        $this->actingAs($hrEmployee->user)
            ->get(route('employee.calendar', ['view' => 'month', 'date' => '2026-08-31']))
            ->assertOk()
            ->assertDontSee('IT Shutdown Day');

        $this->actingAs($hrEmployee->user)
            ->get(route('employee.dtr', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertDontSee('IT Shutdown Day');

        $this->actingAs($itEmployee->user)
            ->get(route('employee.dtr', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('IT Shutdown Day');
    }

    /* -----------------------------------------------------------------
     | Validation
     |-----------------------------------------------------------------*/

    public function test_holiday_requires_an_attendance_effect(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Nameless Holiday',
            'event_type' => CalendarEventType::Holiday->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertSessionHasErrors('attendance_effect');
    }

    public function test_department_audience_requires_a_department(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Department Briefing',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'is_all_day' => '1',
            'audience_type' => EventAudience::Departments->value,
            'status' => EventStatus::Published->value,
        ])->assertSessionHasErrors('department_ids');
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Backwards Event',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-01',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertSessionHasErrors('end_date');
    }

    public function test_attendance_effect_is_discarded_for_non_holiday_types(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Sneaky Meeting',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'attendance_effect' => AttendanceEffect::CompanyHoliday->value,
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        $event = CalendarEvent::query()->firstOrFail();
        $this->assertNull($event->attendance_effect);
        $this->assertFalse(app(HolidayResolver::class)->isNonWorking('2026-09-01'));
    }

    /* -----------------------------------------------------------------
     | Views & notifications
     |-----------------------------------------------------------------*/

    public function test_all_calendar_views_render_for_admin_and_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('views', $this->itDept);

        $this->makeEvent([
            'title' => 'Visible Event',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-22',
        ]);

        foreach (['month', 'week', 'day', 'agenda'] as $view) {
            $this->actingAs($admin)
                ->get(route('admin.calendar.index', ['view' => $view, 'date' => '2026-08-20']))
                ->assertOk()
                ->assertSee('Visible Event');

            $this->actingAs($employee->user)
                ->get(route('employee.calendar', ['view' => $view, 'date' => '2026-08-20']))
                ->assertOk()
                ->assertSee('Visible Event');
        }
    }

    public function test_management_list_and_detail_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->makeEvent(['title' => 'Listed Event']);

        $this->actingAs($admin)->get(route('admin.calendar.events.index'))->assertOk()->assertSee('Listed Event');
        $this->actingAs($admin)->get(route('admin.calendar.events.show', $event))->assertOk()->assertSee('Listed Event');
        $this->actingAs($admin)->get(route('admin.calendar.events.edit', $event))->assertOk();
        $this->actingAs($admin)->get(route('admin.calendar.events.index', ['q' => 'Listed', 'type' => CalendarEventType::CompanyEvent->value]))->assertOk();
    }

    public function test_dashboards_render_upcoming_events(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('dash', $this->itDept);

        $this->makeEvent([
            'title' => 'Upcoming Town Hall',
            'start_date' => ManilaTime::today()->addDays(3)->toDateString(),
            'end_date' => ManilaTime::today()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Upcoming Town Hall');
        $this->actingAs($employee->user)->get(route('employee.dashboard'))->assertOk()->assertSee('Upcoming Town Hall');
    }

    public function test_publishing_with_notify_creates_notifications_for_the_audience(): void
    {
        $admin = User::factory()->admin()->create();
        $itEmployee = $this->makeEmployee('notified', $this->itDept);
        $hrEmployee = $this->makeEmployee('not-notified', $this->hrDept);

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'IT Maintenance Window',
            'event_type' => CalendarEventType::Announcement->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'is_all_day' => '1',
            'audience_type' => EventAudience::Departments->value,
            'department_ids' => [$this->itDept->id],
            'notify_audience' => '1',
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $itEmployee->user_id,
            'action' => 'created',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $hrEmployee->user_id,
        ]);
        $this->assertStringContainsString('New Announcement', AppNotification::query()->where('user_id', $itEmployee->user_id)->value('title'));
    }

    public function test_draft_event_does_not_notify(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('quiet', $this->itDept);

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Draft Announcement',
            'event_type' => CalendarEventType::Announcement->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'notify_audience' => '1',
            'status' => EventStatus::Draft->value,
        ])->assertRedirect();

        $this->assertDatabaseMissing('app_notifications', ['user_id' => $employee->user_id]);
    }

    public function test_published_events_notify_even_without_the_notify_checkbox(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('auto-note', $this->itDept);

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'National Heroes Day',
            'event_type' => CalendarEventType::Holiday->value,
            'attendance_effect' => AttendanceEffect::NoAttendanceRequired->value,
            'start_date' => '2026-08-31',
            'end_date' => '2026-08-31',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $employee->user_id,
            'action' => 'created',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $admin->id,
        ]);
        $this->assertStringContainsString('New Holiday', AppNotification::query()->where('user_id', $employee->user_id)->value('title'));
    }

    public function test_updating_and_cancelling_a_published_event_notifies_the_audience(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('watcher', $this->itDept);
        $event = $this->makeEvent([
            'title' => 'Monthly Company Meeting',
            'event_type' => CalendarEventType::Meeting,
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => false,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $this->actingAs($admin)->put(route('admin.calendar.events.update', $event), [
            'title' => 'Monthly Company Meeting',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => '0',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $employee->user_id,
            'calendar_event_id' => $event->id,
            'action' => 'updated',
        ]);

        $this->actingAs($admin)->put(route('admin.calendar.events.update', $event), [
            'title' => 'Monthly Company Meeting',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => '1',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Cancelled->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $employee->user_id,
            'action' => 'cancelled',
        ]);
    }

    public function test_calendar_mutations_broadcast_to_the_audience_private_channel(): void
    {
        Event::fake([NotificationReceived::class, CalendarEventChanged::class]);

        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('live', $this->itDept);

        $this->actingAs($admin)->post(route('admin.calendar.events.store'), [
            'title' => 'Monthly Company Meeting',
            'event_type' => CalendarEventType::Meeting->value,
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-05',
            'is_all_day' => '0',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'audience_type' => EventAudience::All->value,
            'status' => EventStatus::Published->value,
        ])->assertRedirect();

        Event::assertDispatched(NotificationReceived::class, fn (NotificationReceived $event) => $event->notification->user_id === $employee->user_id);

        Event::assertDispatched(CalendarEventChanged::class, fn (CalendarEventChanged $event) => $event->userId === $employee->user_id && $event->action === 'created');
    }

    public function test_employee_live_feed_omits_events_outside_their_audience(): void
    {
        $itEmployee = $this->makeEmployee('feed-it', $this->itDept);
        $hrEmployee = $this->makeEmployee('feed-hr', $this->hrDept);

        $secret = $this->makeEvent([
            'title' => 'HR Only Briefing',
            'audience_type' => EventAudience::Employees,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
        ]);
        $secret->employees()->sync([$hrEmployee->id]);

        $this->actingAs($itEmployee->user)
            ->getJson(route('employee.calendar.live', ['date' => '2026-08-20', 'view' => 'month']))
            ->assertOk()
            ->assertJsonMissing(['title' => 'HR Only Briefing']);

        $this->actingAs($hrEmployee->user)
            ->getJson(route('employee.calendar.live', ['date' => '2026-08-20', 'view' => 'month']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'HR Only Briefing']);
    }

    public function test_employee_cannot_open_the_admin_live_calendar_feed(): void
    {
        $employee = $this->makeEmployee('denied-live', $this->itDept);

        $this->actingAs($employee->user)
            ->getJson(route('admin.calendar.live'))
            ->assertForbidden();
    }

    public function test_user_cannot_authorise_another_users_notification_channel(): void
    {
        $alice = $this->makeEmployee('alice', $this->itDept);
        $bob = $this->makeEmployee('bob', $this->hrDept);
        $channel = new UserChannel;

        $this->assertTrue($channel->join($alice->user, $alice->user_id));
        $this->assertFalse($channel->join($alice->user, $bob->user_id));

        $alice->user->update(['status' => AccountStatus::Inactive]);
        $this->assertFalse($channel->join($alice->user->fresh(), $alice->user_id));
    }

    public function test_marking_a_notification_read_requires_ownership(): void
    {
        $alice = $this->makeEmployee('note-alice', $this->itDept);
        $bob = $this->makeEmployee('note-bob', $this->hrDept);

        $note = AppNotification::query()->create([
            'user_id' => $alice->user_id,
            'title' => 'Private note',
            'message' => 'Only Alice',
            'type' => 'info',
        ]);

        $this->actingAs($bob->user)
            ->postJson(route('notifications.read', $note))
            ->assertForbidden();

        $this->actingAs($alice->user)
            ->postJson(route('notifications.read', $note))
            ->assertOk()
            ->assertJsonPath('unread', 0);

        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_duplicate_calendar_alerts_are_not_stored_twice(): void
    {
        $employee = $this->makeEmployee('dup-note', $this->itDept);
        $event = $this->makeEvent(['title' => 'Heroes Day']);
        $service = app(NotificationService::class);

        $first = $service->notify($employee->user, '📅 New Holiday: Heroes Day', 'No attendance', 'success', '/employee/calendar', $event->id, 'created');
        $second = $service->notify($employee->user, '📅 New Holiday: Heroes Day', 'No attendance', 'success', '/employee/calendar', $event->id, 'created');

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, AppNotification::query()->where('user_id', $employee->user_id)->count());
    }

    public function test_holiday_name_appears_on_employee_dtr(): void
    {
        $employee = $this->makeEmployee('dtr-view', $this->itDept);

        $this->makeEvent([
            'title' => 'National Heroes Day',
            'event_type' => CalendarEventType::Holiday,
            'attendance_effect' => AttendanceEffect::NoAttendanceRequired,
            'start_date' => '2026-08-31',
            'end_date' => '2026-08-31',
        ]);

        $this->actingAs($employee->user)
            ->get(route('employee.dtr', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('National Heroes Day');
    }

    /* -----------------------------------------------------------------
     | Helpers
     |-----------------------------------------------------------------*/

    private function makeEvent(array $attributes = []): CalendarEvent
    {
        return CalendarEvent::query()->create(array_merge([
            'title' => 'Company Event',
            'event_type' => CalendarEventType::CompanyEvent,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
            'is_all_day' => true,
            'audience_type' => EventAudience::All,
            'status' => EventStatus::Published,
        ], $attributes));
    }

    private function makeEmployee(string $username, Department $department): Employee
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@bacs.test',
            'name' => ucfirst($username).' Test',
        ]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($username).'-001',
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $user->email,
            'department_id' => $department->id,
            'position' => 'Staff',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);
    }
}
