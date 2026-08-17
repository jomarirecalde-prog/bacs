<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $departments = Department::query()
            ->withCount('employees')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->ordered()
            ->paginate(12)
            ->withQueryString();

        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:departments,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $department = Department::query()->create($data + [
            'status' => AccountStatus::Active,
            'sort_order' => (int) Department::query()->max('sort_order') + 1,
        ]);
        $this->auditLogger->log($request->user(), 'department_created', 'Departments', $department->id, "Department {$department->name} created.");

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($department->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    public function deactivate(Request $request, Department $department)
    {
        $department->update(['status' => AccountStatus::Inactive]);

        return back()->with('success', 'Department deactivated.');
    }

    public function show(Department $department)
    {
        $employees = $department->employees()->with('user')->orderBy('last_name')->paginate(15);

        return view('admin.departments.show', compact('department', 'employees'));
    }
}
