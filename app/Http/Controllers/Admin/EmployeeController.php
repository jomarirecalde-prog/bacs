<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use App\Services\AuditLogger;
use App\Services\DirectoryCatalog;
use App\Services\EmployeeQrService;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttendanceService $attendanceService,
        private readonly EmployeeQrService $qr,
        private readonly LeaveBalanceService $leaveBalances,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->with(['user', 'department', 'workSchedule'])
            ->search($request->string('q')->toString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('status'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('status', $request->string('status'))))
            ->orderBy('last_name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => app(DirectoryCatalog::class)->departments(),
            'filters' => $request->only(['q', 'department_id', 'status']),
        ]);
    }

    public function create()
    {
        return view('admin.employees.form', $this->formData());
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $photo = $this->storePhoto($request);

            $user = User::query()->create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => $data['role'] ?? UserRole::Employee->value,
                'status' => $data['account_status'] ?? AccountStatus::Active->value,
                'must_change_password' => false,
            ]);

            $middle = filled($data['middle_name'] ?? null) ? ' '.$data['middle_name'] : '';
            $employee = Employee::query()->create([
                'user_id' => $user->id,
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'full_name' => trim($data['last_name'].', '.$data['first_name'].$middle),
                'email' => $data['email'],
                'contact_number' => $data['contact_number'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'position' => $data['position'] ?? null,
                'employment_status' => $data['employment_status'],
                'date_hired' => $data['date_hired'] ?? null,
                'photo' => $photo,
                'work_schedule_id' => $data['work_schedule_id'] ?? null,
            ]);

            $this->auditLogger->log($request->user(), 'employee_created', 'Employees', $employee->id, "Employee {$employee->fullName()} created.");
            $this->qr->issue($employee);
            $this->leaveBalances->initializeForEmployee($employee, null, $request->user());

            return $employee;
        });

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Employee account created.');
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);
        $employee->load(['user', 'department', 'workSchedule', 'attendance' => fn ($q) => $q->latest('attendance_date')->limit(10)]);
        $summary = $this->attendanceService->monthlySummary($employee);

        return view('admin.employees.show', compact('employee', 'summary'));
    }

    public function edit(Employee $employee)
    {
        $employee->load('user');

        return view('admin.employees.form', array_merge($this->formData(), compact('employee')));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        DB::transaction(function () use ($request, $employee) {
            $data = $request->validated();
            $photo = $this->storePhoto($request, $employee);

            $userData = [
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'username' => $data['username'],
                'role' => $data['role'] ?? $employee->user->role,
                'status' => $data['account_status'] ?? $employee->user->status,
            ];

            if (filled($data['password'] ?? null)) {
                $userData['password'] = $data['password'];
            }

            $employee->user->update($userData);

            $middle = filled($data['middle_name'] ?? null) ? ' '.$data['middle_name'] : '';
            $employee->update([
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'full_name' => trim($data['last_name'].', '.$data['first_name'].$middle),
                'email' => $data['email'],
                'contact_number' => $data['contact_number'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'position' => $data['position'] ?? null,
                'employment_status' => $data['employment_status'],
                'date_hired' => $data['date_hired'] ?? null,
                'photo' => $photo ?? $employee->photo,
                'work_schedule_id' => $data['work_schedule_id'] ?? null,
            ]);

            $this->auditLogger->log($request->user(), 'employee_updated', 'Employees', $employee->id, "Employee {$employee->fullName()} updated.");
        });

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function deactivate(Request $request, Employee $employee)
    {
        $employee->user->update(['status' => AccountStatus::Inactive]);
        $this->auditLogger->log($request->user(), 'employee_deactivated', 'Employees', $employee->id, "Employee {$employee->fullName()} deactivated.");

        return back()->with('success', 'Employee account deactivated. Historical DTR records were preserved.');
    }

    public function activate(Request $request, Employee $employee)
    {
        $employee->user->update(['status' => AccountStatus::Active]);
        $this->auditLogger->log($request->user(), 'employee_updated', 'Employees', $employee->id, "Employee {$employee->fullName()} reactivated.");

        return back()->with('success', 'Employee account activated.');
    }

    private function formData(): array
    {
        return [
            'departments' => Department::query()->active()->ordered()->get(),
            'schedules' => WorkSchedule::query()->active()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'accountStatuses' => AccountStatus::cases(),
            'employmentStatuses' => EmploymentStatus::cases(),
        ];
    }

    private function storePhoto(Request $request, ?Employee $employee = null): ?string
    {
        if (! $request->hasFile('photo')) {
            return $employee?->photo;
        }

        if ($employee?->photo) {
            Storage::disk('public')->delete($employee->photo);
        }

        return $request->file('photo')->store('photos', 'public');
    }
}
