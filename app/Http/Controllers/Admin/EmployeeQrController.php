<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Services\EmployeeQrService;
use Illuminate\Http\Request;

class EmployeeQrController extends Controller
{
    public function __construct(
        private readonly EmployeeQrService $qr,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);
        $token = $this->qr->ensure($employee);

        return view('admin.employees.qr', [
            'employee' => $employee->load(['department', 'user']),
            'token' => $token,
            'plain' => $token->plainToken(),
        ]);
    }

    public function generate(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        if ($employee->activeQrToken()) {
            return back()->with('error', 'This employee already has an active QR code.');
        }

        $this->qr->issue($employee);
        $this->auditLogger->log($request->user(), 'qr_generated', 'Employees', $employee->id, "QR code generated for {$employee->fullName()}.");

        return back()->with('success', 'Employee QR code generated.');
    }

    public function regenerate(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $this->qr->regenerate($employee);
        $this->auditLogger->log($request->user(), 'qr_regenerated', 'Employees', $employee->id, "QR code regenerated for {$employee->fullName()}. The previous code is now invalid.");

        return back()->with('success', 'QR code regenerated. The previous code is now invalid.');
    }

    public function disable(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $this->qr->disable($employee);
        $this->auditLogger->log($request->user(), 'qr_disabled', 'Employees', $employee->id, "QR code disabled for {$employee->fullName()}.");

        return back()->with('success', 'Employee QR code disabled.');
    }

    public function enable(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $this->qr->enable($employee);
        $this->auditLogger->log($request->user(), 'qr_enabled', 'Employees', $employee->id, "QR code enabled for {$employee->fullName()}.");

        return back()->with('success', 'Employee QR code enabled.');
    }

    public function print(Employee $employee)
    {
        $this->authorize('view', $employee);
        $token = $this->qr->ensure($employee);

        return view('qr.print', [
            'employee' => $employee->load('department'),
            'plain' => $token->plainToken(),
        ]);
    }
}
