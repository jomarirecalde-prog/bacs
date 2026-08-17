@extends('layouts.app')

@section('title', isset($employee) ? 'Edit Employee' : 'Add Employee')
@section('page-title', isset($employee) ? 'Edit Employee' : 'Add Employee')

@section('content')
<div class="card p-6 max-w-5xl">
    <form method="POST" action="{{ isset($employee) ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
        @csrf
        @if (isset($employee)) @method('PUT') @endif

        <div><label class="label">Employee Number</label><input class="input" name="employee_number" value="{{ old('employee_number', $employee->employee_number ?? '') }}" required></div>
        <div><label class="label">Position</label><input class="input" name="position" value="{{ old('position', $employee->position ?? '') }}"></div>
        <div><label class="label">First Name</label><input class="input" name="first_name" value="{{ old('first_name', $employee->first_name ?? '') }}" required></div>
        <div><label class="label">Middle Name</label><input class="input" name="middle_name" value="{{ old('middle_name', $employee->middle_name ?? '') }}"></div>
        <div><label class="label">Last Name</label><input class="input" name="last_name" value="{{ old('last_name', $employee->last_name ?? '') }}" required></div>
        <div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email', $employee->email ?? '') }}" required></div>
        <div><label class="label">Contact Number</label><input class="input" name="contact_number" value="{{ old('contact_number', $employee->contact_number ?? '') }}"></div>
        <div>
            <label class="label">Department</label>
            <select class="input" name="department_id">
                <option value="">Select department</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(old('department_id', $employee->department_id ?? '') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Employment Status</label>
            <select class="input" name="employment_status" required>
                @foreach ($employmentStatuses as $status)
                    <option value="{{ $status->value }}" @selected(old('employment_status', $employee->employment_status?->value ?? 'regular') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Date Hired</label><input class="input" type="date" name="date_hired" value="{{ old('date_hired', isset($employee) ? $employee->date_hired?->toDateString() : '') }}"></div>
        <div>
            <label class="label">Work Schedule</label>
            <select class="input" name="work_schedule_id">
                <option value="">Default schedule</option>
                @foreach ($schedules as $schedule)
                    <option value="{{ $schedule->id }}" @selected(old('work_schedule_id', $employee->work_schedule_id ?? '') == $schedule->id)>{{ $schedule->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Username</label><input class="input" name="username" value="{{ old('username', $employee->user->username ?? '') }}" required></div>
        <div><label class="label">Password {{ isset($employee) ? '(leave blank to keep)' : '' }}</label><input class="input" type="password" name="password" {{ isset($employee) ? '' : 'required' }}></div>
        <div><label class="label">Confirm Password</label><input class="input" type="password" name="password_confirmation"></div>
        <div>
            <label class="label">Role</label>
            <select class="input" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $employee->user->role->value ?? 'employee') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Account Status</label>
            <select class="input" name="account_status" required>
                @foreach ($accountStatuses as $status)
                    <option value="{{ $status->value }}" @selected(old('account_status', $employee->user->status->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="label">Profile Photo</label>
            <input class="input" type="file" name="photo" accept="image/*">
        </div>
        <div class="md:col-span-2 flex gap-2">
            <button class="btn-primary">Save employee</button>
            <a href="{{ route('admin.employees.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
