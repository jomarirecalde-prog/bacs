<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeQrService;
use Illuminate\Http\Request;

class QrCodeController extends Controller
{
    public function __construct(private readonly EmployeeQrService $qr) {}

    public function show(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        $token = $this->qr->ensure($employee);

        return view('employee.qr', [
            'employee' => $employee->load('department'),
            'token' => $token,
            'plain' => $token->plainToken(),
        ]);
    }

    public function print(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $token = $this->qr->ensure($employee);

        return view('qr.print', [
            'employee' => $employee->load('department'),
            'plain' => $token->plainToken(),
        ]);
    }
}
