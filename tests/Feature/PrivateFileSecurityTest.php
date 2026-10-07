<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\TravelOrderStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\TravelOrder;
use App\Models\TravelOrderAttachment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => 'active',
        ]);

        Department::query()->create(['name' => 'Ops', 'status' => 'active']);
    }

    public function test_public_storage_route_is_not_registered(): void
    {
        $response = $this->get('/storage/travel-order-attachments/1/test.pdf');

        $this->assertFalse($response->isOk());
        $this->assertContains($response->status(), [404, 403]);
    }

    public function test_travel_attachment_download_requires_authentication(): void
    {
        Storage::fake('local');
        [$owner, $stranger] = $this->twoEmployees();
        $order = $this->travelOrderFor($owner);
        $path = 'travel-order-attachments/'.$order->id.'/doc.pdf';
        Storage::disk('local')->put($path, 'secret-bytes');
        $attachment = TravelOrderAttachment::query()->create([
            'travel_order_id' => $order->id,
            'file_name' => 'doc.pdf',
            'file_path' => $path,
            'uploaded_by' => $owner->user_id,
        ]);

        $this->get(route('travel-orders.attachments.download', [$order, $attachment]))
            ->assertRedirect(route('login'));

        $this->actingAs($stranger->user)
            ->get(route('travel-orders.attachments.download', [$order, $attachment]))
            ->assertForbidden();

        $this->actingAs($owner->user)
            ->get(route('travel-orders.attachments.download', [$order, $attachment]))
            ->assertOk();
    }

    public function test_employee_photo_is_served_through_authenticated_route(): void
    {
        Storage::fake('local');
        $employee = $this->makeEmployee('photosec');
        $path = 'photos/employees/'.$employee->id.'/sample.jpg';
        Storage::disk('local')->put($path, 'jpeg-bytes');
        $employee->update(['photo' => $path]);

        $this->get(route('employee-photos.show', ['path' => $path]))
            ->assertUnauthorized();

        $this->actingAs($employee->user)
            ->get(route('employee-photos.show', ['path' => $path]))
            ->assertOk();
    }

    private function makeEmployee(string $slug): Employee
    {
        $user = User::factory()->create(['username' => $slug]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($slug),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
    }

    /** @return array{0: Employee, 1: Employee} */
    private function twoEmployees(): array
    {
        return [$this->makeEmployee('to-owner'), $this->makeEmployee('to-other')];
    }

    private function travelOrderFor(Employee $requester): TravelOrder
    {
        return TravelOrder::query()->create([
            'travel_order_number' => 'TO-2026-0001',
            'requester_id' => $requester->id,
            'department_id' => $requester->department_id,
            'status' => TravelOrderStatus::Draft,
            'purpose' => 'Site visit',
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
        ]);
    }
}
