<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request)
    {
        $user = $request->user()->load('employee.department', 'employee.workSchedule');

        return view('profile.show', compact('user'));
    }

    public function update(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $data = $request->validate([
            'contact_number' => ['nullable', 'string', 'max:30'],
        ]);

        $employee->update([
            'contact_number' => $data['contact_number'] ?? null,
        ]);

        $this->auditLogger->log($request->user(), 'profile_updated', 'Profile', $employee->id, 'Permitted profile information updated.');

        return back()->with('success', 'Profile updated.');
    }

    public function editPassword()
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        $this->auditLogger->log($request->user(), 'password_changed', 'Profile', $request->user()->id, 'Password changed.');

        return redirect()->route('home')->with('success', 'Password updated successfully.');
    }
}
