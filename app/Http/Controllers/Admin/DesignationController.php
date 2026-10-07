<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SalaryType;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Services\DirectoryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Designation::class);

        $designations = Designation::query()
            ->with('department:id,name')
            ->withCount('employees')
            ->search($request->string('q')->toString())
            ->orderBy('designation_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.designations.index', compact('designations'));
    }

    public function create()
    {
        $this->authorize('create', Designation::class);

        return view('admin.designations.create', [
            'departments' => app(DirectoryCatalog::class)->departments(),
            'salaryTypes' => SalaryType::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Designation::class);

        $data = $this->validated($request);
        $code = $this->resolveCode($data['designation_code'] ?? null, $data['designation_name']);

        $designation = Designation::query()->create([
            ...$this->compensationPayload($data),
            'designation_code' => $code,
            'designation_name' => $data['designation_name'],
            'department_id' => $data['department_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit->log(
            $request->user(),
            'designation_created',
            'Designations',
            $designation->id,
            "Designation {$designation->designation_name} created.",
            $request,
            ['designation_id' => $designation->id],
        );

        return redirect()
            ->route('admin.designations.show', $designation)
            ->with('success', 'Designation saved.');
    }

    public function show(Designation $designation)
    {
        $this->authorize('view', $designation);

        $designation->load('department:id,name');

        $employees = Employee::query()
            ->where('designation_id', $designation->id)
            ->with(['department:id,name', 'user:id,username,status'])
            ->orderBy('full_name')
            ->paginate(15);

        return view('admin.designations.show', compact('designation', 'employees'));
    }

    public function edit(Designation $designation)
    {
        $this->authorize('update', $designation);

        $designation->load('department:id,name');

        return view('admin.designations.edit', [
            'designation' => $designation,
            'departments' => app(DirectoryCatalog::class)->departments(),
            'salaryTypes' => SalaryType::cases(),
        ]);
    }

    public function update(Request $request, Designation $designation)
    {
        $this->authorize('update', $designation);

        $before = $designation->only([
            'default_pay_type',
            'default_daily_rate',
            'default_hourly_rate',
            'default_basic_salary',
            'default_semi_monthly_salary',
        ]);

        $data = $this->validated($request, $designation->id);

        $designation->update([
            ...$this->compensationPayload($data),
            'designation_code' => $data['designation_code'],
            'designation_name' => $data['designation_name'],
            'department_id' => $data['department_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->log(
            $request->user(),
            'designation_updated',
            'Designations',
            $designation->id,
            "Designation {$designation->designation_name} updated.",
            $request,
            ['before' => $before, 'after' => $designation->only(array_keys($before))],
        );

        return redirect()
            ->route('admin.designations.show', $designation)
            ->with('success', 'Designation updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'designation_name' => [
                'required', 'string', 'max:150',
                Rule::unique('designations', 'designation_name')->ignore($ignoreId),
            ],
            'designation_code' => [
                $ignoreId ? 'required' : 'nullable',
                'string', 'max:64',
                Rule::unique('designations', 'designation_code')->ignore($ignoreId),
            ],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_pay_type' => ['nullable', Rule::enum(SalaryType::class)],
            'default_basic_salary' => ['nullable', 'numeric', 'min:0'],
            'default_semi_monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'default_daily_rate' => ['nullable', 'numeric', 'min:0'],
            'default_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'default_working_hours_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'default_working_days_per_period' => ['nullable', 'integer', 'min:1', 'max:31'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function compensationPayload(array $data): array
    {
        return [
            'default_pay_type' => $data['default_pay_type'] ?? null,
            'default_basic_salary' => $this->nullableAmount($data['default_basic_salary'] ?? null),
            'default_semi_monthly_salary' => $this->nullableAmount($data['default_semi_monthly_salary'] ?? null),
            'default_daily_rate' => $this->nullableAmount($data['default_daily_rate'] ?? null),
            'default_hourly_rate' => $this->nullableAmount($data['default_hourly_rate'] ?? null),
            'default_working_hours_per_day' => $data['default_working_hours_per_day'] ?? 8,
            'default_working_days_per_period' => $data['default_working_days_per_period'] ?? null,
        ];
    }

    private function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function resolveCode(?string $code, string $name): string
    {
        $code = $code !== null && $code !== '' ? Str::upper(Str::slug($code, '_')) : Str::upper(Str::slug($name, '_'));
        if ($code === '') {
            $code = 'DESIGNATION_'.time();
        }

        $base = $code;
        $suffix = 1;
        while (Designation::query()->where('designation_code', $code)->exists()) {
            $code = Str::limit($base, 44, '').'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
