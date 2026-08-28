@extends('layouts.app')

@section('title', isset($employee) ? 'Edit Employee' : 'Add Employee')
@section('page-title', isset($employee) ? 'Edit Employee' : 'Add Employee')
@section('page-subtitle', isset($employee) ? 'Update employee record and account access' : 'Create an employee record and login account')

@section('content')
<form method="POST" action="{{ isset($employee) ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" enctype="multipart/form-data" class="max-w-5xl space-y-6">
    @csrf
    @if (isset($employee)) @method('PUT') @endif

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">Employee Details</h2>
                <p class="mt-0.5 text-xs text-muted">Personal information and assignment</p>
            </div>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-2">
            <div>
                <label class="label" for="employee_number">Employee Number</label>
                <input id="employee_number" class="input @error('employee_number') input-error @enderror" name="employee_number" value="{{ old('employee_number', $employee->employee_number ?? '') }}" required>
                @error('employee_number')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="position">Position</label>
                <input id="position" class="input" name="position" value="{{ old('position', $employee->position ?? '') }}">
            </div>
            <div>
                <label class="label" for="first_name">First Name</label>
                <input id="first_name" class="input @error('first_name') input-error @enderror" name="first_name" value="{{ old('first_name', $employee->first_name ?? '') }}" required>
                @error('first_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="middle_name">Middle Name</label>
                <input id="middle_name" class="input" name="middle_name" value="{{ old('middle_name', $employee->middle_name ?? '') }}">
            </div>
            <div>
                <label class="label" for="last_name">Last Name</label>
                <input id="last_name" class="input @error('last_name') input-error @enderror" name="last_name" value="{{ old('last_name', $employee->last_name ?? '') }}" required>
                @error('last_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" class="input @error('email') input-error @enderror" type="email" name="email" value="{{ old('email', $employee->email ?? '') }}" required>
                @error('email')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="contact_number">Contact Number</label>
                <input id="contact_number" class="input" name="contact_number" value="{{ old('contact_number', $employee->contact_number ?? '') }}">
            </div>
            <div>
                <label class="label" for="department_id">Department</label>
                <select id="department_id" class="select" name="department_id">
                    <option value="">Select department</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(old('department_id', $employee->department_id ?? '') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="employment_status">Employment Status</label>
                <select id="employment_status" class="select" name="employment_status" required>
                    @foreach ($employmentStatuses as $status)
                        <option value="{{ $status->value }}" @selected(old('employment_status', $employee->employment_status?->value ?? 'regular') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="date_hired">Date Hired</label>
                <input id="date_hired" class="input" type="date" name="date_hired" value="{{ old('date_hired', isset($employee) ? $employee->date_hired?->toDateString() : '') }}">
            </div>
            <div>
                <label class="label" for="work_schedule_id">Work Schedule</label>
                <select id="work_schedule_id" class="select" name="work_schedule_id">
                    <option value="">Default schedule</option>
                    @foreach ($schedules as $schedule)
                        <option value="{{ $schedule->id }}" @selected(old('work_schedule_id', $employee->work_schedule_id ?? '') == $schedule->id)>{{ $schedule->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="photo">Profile Photo</label>
                <input id="photo" class="input file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-brand-700" type="file" name="photo" accept="image/*">
            </div>
        </div>
    </div>

    <div class="card card-accent-info overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">Account Access</h2>
                <p class="mt-0.5 text-xs text-muted">Login credentials, role, and account state</p>
            </div>
        </div>
        <div class="grid gap-4 p-5 md:grid-cols-2">
            <div>
                <label class="label" for="username">Username</label>
                <input id="username" class="input @error('username') input-error @enderror" name="username" value="{{ old('username', $employee->user->username ?? '') }}" required>
                @error('username')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="role">Role</label>
                <select id="role" class="select" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $employee->user->role->value ?? 'employee') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" class="input @error('password') input-error @enderror" type="password" name="password" autocomplete="new-password" {{ isset($employee) ? '' : 'required' }}>
                @if (isset($employee))
                    <p class="hint">Leave blank to keep the current password.</p>
                @endif
                @error('password')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password">
            </div>
            <div class="md:col-span-2">
                <label class="label" for="account_status">Account Status</label>
                <select id="account_status" class="select md:max-w-xs" name="account_status" required>
                    @foreach ($accountStatuses as $status)
                        <option value="{{ $status->value }}" @selected(old('account_status', $employee->user->status->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card-footer flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">Save employee</button>
            <a href="{{ route('admin.employees.index') }}" class="btn-outline">Cancel</a>
        </div>
    </div>
</form>
@endsection
