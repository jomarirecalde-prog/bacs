<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminAccountRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class AdminAccountController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(User $admin)
    {
        $this->ensureSuperAdmin($admin);

        return view('admin.settings.admin-edit', compact('admin'));
    }

    public function update(UpdateAdminAccountRequest $request, User $admin)
    {
        $this->ensureSuperAdmin($admin);

        $data = $request->validated();

        $admin->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'status' => $data['status'],
        ]);

        if (filled($data['password'] ?? null)) {
            $admin->password = $data['password'];
            $admin->must_change_password = false;
            $admin->password_changed_at = now();
        }

        $admin->save();

        $this->auditLogger->log(
            $request->user(),
            'admin_account_updated',
            'Settings',
            $admin->id,
            "Super admin account {$admin->username} updated.",
        );

        return redirect()->route('admin.settings.index')->with('success', 'Super admin account updated.');
    }

    public function destroy(Request $request, User $admin)
    {
        $this->ensureSuperAdmin($admin);

        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if (User::query()->where('role', UserRole::Admin)->count() <= 1) {
            return back()->with('error', 'At least one super admin account must remain.');
        }

        $username = $admin->username;
        $adminId = $admin->id;

        try {
            $admin->delete();
        } catch (QueryException) {
            return back()->with('error', 'This account cannot be deleted because it is referenced by system records. Deactivate it instead.');
        }

        $this->auditLogger->log(
            $request->user(),
            'admin_account_deleted',
            'Settings',
            $adminId,
            "Super admin account {$username} deleted.",
        );

        return back()->with('success', 'Super admin account removed.');
    }

    private function ensureSuperAdmin(User $user): void
    {
        abort_unless($user->isAdmin(), 404);
    }
}
