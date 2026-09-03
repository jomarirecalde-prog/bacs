<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfilePhotoRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\AuditLogger;
use App\Services\ProfileService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profiles,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user()->load('employee.department');

        return view('profile.show', [
            'user' => $user,
            'profile' => $this->profiles->profilePayload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($this->profiles->profilePayload($request->user()));
    }

    public function update(UpdateProfileRequest $request)
    {
        $employee = $this->profiles->updatePersonalInfo($request->user(), $request->validated());
        $payload = $this->profiles->profilePayload($request->user()->fresh(['employee.department']));

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Profile updated successfully.',
                'profile' => $payload,
            ]);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(UpdateProfilePhotoRequest $request)
    {
        try {
            $employee = $this->profiles->storePhoto($request->user(), $request->file('photo'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 503);
        }

        $payload = $this->profiles->profilePayload($request->user()->fresh(['employee.department']));

        return response()->json([
            'ok' => true,
            'message' => 'Profile picture updated.',
            'photo_url' => $employee->photoUrl(),
            'profile' => $payload,
        ]);
    }

    public function removePhoto(Request $request)
    {
        abort_unless($request->user()->employee, 403);

        $employee = $this->profiles->removePhoto($request->user());
        $payload = $this->profiles->profilePayload($request->user()->fresh(['employee.department']));

        return response()->json([
            'ok' => true,
            'message' => 'Profile picture removed.',
            'photo_url' => $employee->photoUrl(),
            'profile' => $payload,
        ]);
    }

    public function editPassword()
    {
        return redirect()->to(route('profile.show').'#password');
    }

    public function updatePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $currentPassword = $request->validated('current_password');

        Auth::logoutOtherDevices($currentPassword);

        $user->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'password_changed_at' => ManilaTime::now(),
        ]);

        $request->session()->regenerate();

        $this->auditLogger->log($user, 'password_changed', 'Profile', $user->id, 'Password changed.');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Password updated successfully.',
                'password_changed_at' => $user->fresh()->password_changed_at?->toIso8601String(),
                'csrf_token' => csrf_token(),
            ]);
        }

        return redirect()->route('home')->with('success', 'Password updated successfully.');
    }
}
