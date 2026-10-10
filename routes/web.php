<?php

use App\Http\Controllers\Admin\AttendanceCorrectionController as AdminAttendanceCorrectionController;
use App\Http\Controllers\Admin\AttendanceStationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Admin\CalendarEventController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\DtrController as AdminDtrController;
use App\Http\Controllers\Admin\EmailNotificationController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeQrController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\ApprovalWorkflowController;
use App\Http\Controllers\Admin\Payroll\EmployeeSalaryController as PayrollEmployeeSalaryController;
use App\Http\Controllers\Admin\Payroll\PayrollDashboardController;
use App\Http\Controllers\Admin\Payroll\PayrollEmployeeController;
use App\Http\Controllers\Admin\Payroll\PayrollPeriodController;
use App\Http\Controllers\Admin\Payroll\OvertimeRequestController as PayrollOvertimeRequestController;
use App\Http\Controllers\Admin\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\Admin\Payroll\PayrollRegisterController;
use App\Http\Controllers\Admin\Payroll\PayrollSettingsController;
use App\Http\Controllers\Employee\PayrollController as EmployeePayrollController;
use App\Http\Controllers\Employee\SalaryController as EmployeeSalaryController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\PardonApprovalController;
use App\Http\Controllers\Admin\StationMonitoringController;
use App\Http\Controllers\Auth\AccountPasswordSetupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClockController;
use App\Http\Controllers\Employee\AttendanceCorrectionController as EmployeeAttendanceCorrectionController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Employee\CalendarController as EmployeeCalendarController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\DtrController as EmployeeDtrController;
use App\Http\Controllers\Employee\QrCodeController as EmployeeQrCodeController;
use App\Http\Controllers\Admin\LeaveApplicationController as AdminLeaveApplicationController;
use App\Http\Controllers\Admin\LeaveEntitlementController;
use App\Http\Controllers\Admin\LeavePolicyController;
use App\Http\Controllers\Admin\LeaveReportController;
use App\Http\Controllers\Admin\LeaveWorkflowController;
use App\Http\Controllers\Employee\LeaveApplicationController as EmployeeLeaveApplicationController;
use App\Http\Controllers\EmployeePhotoController;
use App\Http\Controllers\Admin\TravelOrderController as AdminTravelOrderController;
use App\Http\Controllers\Employee\TravelOrderController as EmployeeTravelOrderController;
use App\Http\Controllers\LeaveApprovalController;
use App\Http\Controllers\TravelOrderApprovalController;
use App\Http\Controllers\OfficialTimeApprovalController;
use App\Http\Controllers\Employee\OfficialTimeController as EmployeeOfficialTimeController;
use App\Http\Controllers\Admin\OfficialTimeController as AdminOfficialTimeController;
use App\Http\Controllers\Admin\OfficialTimeTypeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TravelOrderAttachmentController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Station\DashboardController as StationDashboardController;
use App\Http\Controllers\Station\HeartbeatController as StationHeartbeatController;
use App\Http\Controllers\Station\LoginController as StationLoginController;
use App\Http\Controllers\Station\ScanController as StationScanController;
use App\Http\Controllers\Station\SettingsController as StationSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home');
    }

    return redirect()->route('login');
});

Route::get('/employee-photos/{path}', [EmployeePhotoController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['web', 'photo.access'])
    ->name('employee-photos.show');

Route::prefix('attendance-station')->name('station.')->group(function () {
    Route::middleware('guest:station')->group(function () {
        Route::get('/login', [StationLoginController::class, 'show'])->name('login');
        Route::post('/login', [StationLoginController::class, 'store'])->middleware('throttle:station-login')->name('login.store');
    });

    Route::middleware(['auth:station', 'station.device'])->group(function () {
        Route::get('/', [StationDashboardController::class, 'index'])->name('dashboard');
        Route::post('/scan', [StationScanController::class, 'store'])->middleware('throttle:station-scan')->name('scan');
        Route::post('/heartbeat', [StationHeartbeatController::class, 'store'])->name('heartbeat');
        Route::get('/settings', [StationSettingsController::class, 'index'])->name('settings');
        Route::post('/logout', [StationLoginController::class, 'destroy'])->name('logout');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/welcome/set-password/{user}', [AccountPasswordSetupController::class, 'show'])
        ->middleware('signed')
        ->name('password.setup.show');
    Route::post('/welcome/set-password/{user}', [AccountPasswordSetupController::class, 'store'])
        ->middleware('signed')
        ->name('password.setup.store');
});

Route::middleware(['auth', 'account.active', 'password.changed'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/home', function () {
        return auth()->user()->isManagement()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('employee.dashboard');
    })->name('home');

    Route::get('/server-time', [ClockController::class, 'serverTime'])->name('server-time');
    Route::get('/session/heartbeat', [SessionController::class, 'heartbeat'])->name('session.heartbeat');
    Route::post('/session/extend', [SessionController::class, 'extend'])->name('session.extend');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/me', [ProfileController::class, 'me'])->name('profile.me');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('profile.password');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('/travel-orders/{travelOrder}/attachments/{attachment}', [TravelOrderAttachmentController::class, 'show'])
        ->name('travel-orders.attachments.download');

    Route::post('/attendance/time-in', [ClockController::class, 'timeIn'])->middleware('throttle:clock')->name('attendance.time-in');
    Route::post('/attendance/time-out', [ClockController::class, 'timeOut'])->middleware('throttle:clock')->name('attendance.time-out');
    Route::get('/attendance/today', [ClockController::class, 'today'])->name('attendance.today');

    Route::prefix('employee')->name('employee.')->group(function () {
        Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
        Route::get('/attendance', [EmployeeAttendanceController::class, 'index'])->name('attendance');
        Route::get('/attendance-corrections', [EmployeeAttendanceCorrectionController::class, 'index'])->name('attendance-corrections.index');
        Route::get('/attendance-corrections/create', [EmployeeAttendanceCorrectionController::class, 'create'])->name('attendance-corrections.create');
        Route::get('/attendance-corrections/day-preview', [EmployeeAttendanceCorrectionController::class, 'dayPreview'])->name('attendance-corrections.day-preview');
        Route::post('/attendance-corrections', [EmployeeAttendanceCorrectionController::class, 'store'])->name('attendance-corrections.store');
        Route::get('/attendance-corrections/{correction}', [EmployeeAttendanceCorrectionController::class, 'show'])->name('attendance-corrections.show');
        Route::post('/attendance-corrections/{correction}/cancel', [EmployeeAttendanceCorrectionController::class, 'cancel'])->name('attendance-corrections.cancel');
        Route::get('/dtr', [EmployeeDtrController::class, 'index'])->name('dtr');
        Route::get('/dtr/export', [EmployeeDtrController::class, 'export'])->name('dtr.export');
        Route::get('/dtr/print', [EmployeeDtrController::class, 'print'])->name('dtr.print');
        Route::get('/dtr/{employee}', [EmployeeDtrController::class, 'show'])->name('dtr.show');
        Route::get('/qr-code', [EmployeeQrCodeController::class, 'show'])->name('qr');
        Route::get('/qr-code/print', [EmployeeQrCodeController::class, 'print'])->name('qr.print');

        // Read-only company calendar. No write routes exist for employees.
        Route::get('/calendar', [EmployeeCalendarController::class, 'index'])->name('calendar');
        Route::get('/calendar/live', [EmployeeCalendarController::class, 'live'])->name('calendar.live');

        Route::get('/leave', [EmployeeLeaveApplicationController::class, 'index'])->name('leave.index');
        Route::get('/leave/apply', [EmployeeLeaveApplicationController::class, 'create'])->name('leave.apply');
        Route::get('/leave/calendar', [EmployeeLeaveApplicationController::class, 'calendar'])->name('leave.calendar');
        Route::get('/leave/balances', [EmployeeLeaveApplicationController::class, 'balances'])->name('leave.balances');
        Route::get('/leave/balances/adjustments', [EmployeeLeaveApplicationController::class, 'adjustmentHistory'])->name('leave.balances.adjustments');
        Route::get('/leave/preview-days', [EmployeeLeaveApplicationController::class, 'previewDays'])->name('leave.preview-days');
        Route::post('/leave', [EmployeeLeaveApplicationController::class, 'store'])->name('leave.store');
        Route::get('/leave/{application}', [EmployeeLeaveApplicationController::class, 'show'])->name('leave.show');
        Route::get('/leave/{application}/pdf', [EmployeeLeaveApplicationController::class, 'pdf'])->name('leave.pdf');
        Route::get('/leave/{application}/print', [EmployeeLeaveApplicationController::class, 'print'])->name('leave.print');
        Route::post('/leave/{application}/cancel', [EmployeeLeaveApplicationController::class, 'cancel'])->name('leave.cancel');

        Route::get('/travel-orders', [EmployeeTravelOrderController::class, 'index'])->name('travel-orders.index');
        Route::get('/travel-orders/create', [EmployeeTravelOrderController::class, 'create'])->name('travel-orders.create');
        Route::get('/travel-orders/employees/search', [EmployeeTravelOrderController::class, 'searchEmployees'])->name('travel-orders.employees.search');
        Route::post('/travel-orders', [EmployeeTravelOrderController::class, 'store'])->name('travel-orders.store');
        Route::get('/travel-orders/{travelOrder}', [EmployeeTravelOrderController::class, 'show'])->name('travel-orders.show');
        Route::get('/travel-orders/{travelOrder}/edit', [EmployeeTravelOrderController::class, 'edit'])->name('travel-orders.edit');
        Route::put('/travel-orders/{travelOrder}', [EmployeeTravelOrderController::class, 'update'])->name('travel-orders.update');
        Route::post('/travel-orders/{travelOrder}/cancel', [EmployeeTravelOrderController::class, 'cancel'])->name('travel-orders.cancel');
        Route::get('/travel-orders/{travelOrder}/pdf', [EmployeeTravelOrderController::class, 'pdf'])->name('travel-orders.pdf');
        Route::get('/travel-orders/{travelOrder}/print', [EmployeeTravelOrderController::class, 'print'])->name('travel-orders.print');

        Route::get('/official-time', [EmployeeOfficialTimeController::class, 'index'])->name('official-time.index');
        Route::get('/official-time/create', [EmployeeOfficialTimeController::class, 'create'])->name('official-time.create');
        Route::post('/official-time', [EmployeeOfficialTimeController::class, 'store'])->name('official-time.store');
        Route::get('/official-time/{officialTimeRequest}', [EmployeeOfficialTimeController::class, 'show'])->name('official-time.show');
        Route::get('/official-time/{officialTimeRequest}/edit', [EmployeeOfficialTimeController::class, 'edit'])->name('official-time.edit');
        Route::put('/official-time/{officialTimeRequest}', [EmployeeOfficialTimeController::class, 'update'])->name('official-time.update');
        Route::post('/official-time/{officialTimeRequest}/cancel', [EmployeeOfficialTimeController::class, 'cancel'])->name('official-time.cancel');
        Route::get('/official-time/{officialTimeRequest}/attachment', [EmployeeOfficialTimeController::class, 'attachment'])->name('official-time.attachment');

        Route::get('/salary', [EmployeeSalaryController::class, 'index'])->name('salary.index');
        Route::get('/salary/{payrollEmployee}', [EmployeeSalaryController::class, 'show'])->name('salary.show');

        Route::get('/payroll', [EmployeePayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/{payrollEmployee}', [EmployeePayrollController::class, 'show'])->name('payroll.show');
        Route::get('/payroll/{payrollEmployee}/pdf', [EmployeePayrollController::class, 'pdf'])->name('payroll.pdf');
        Route::get('/payroll/{payrollEmployee}/print', [EmployeePayrollController::class, 'print'])->name('payroll.print');
    });

    Route::prefix('overtime/approvals')->name('overtime.approvals.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OvertimeApprovalController::class, 'index'])->name('index');
        Route::get('/{overtimeRequest}', [\App\Http\Controllers\OvertimeApprovalController::class, 'show'])->name('show');
        Route::post('/{overtimeRequest}/decide', [\App\Http\Controllers\OvertimeApprovalController::class, 'decide'])->name('decide');
    });

    Route::prefix('pardon/approvals')->name('pardon.approvals.')->group(function () {
        Route::get('/', [PardonApprovalController::class, 'index'])->name('index');
        Route::get('/{correction}', [PardonApprovalController::class, 'show'])->name('show');
        Route::post('/{correction}/decide', [PardonApprovalController::class, 'decide'])->name('decide');
    });

    Route::prefix('official-time/approvals')->name('official-time.approvals.')->group(function () {
        Route::get('/', [OfficialTimeApprovalController::class, 'index'])->name('index');
        Route::get('/history', [OfficialTimeApprovalController::class, 'history'])->name('history');
        Route::get('/{officialTimeRequest}', [OfficialTimeApprovalController::class, 'show'])->name('show');
        Route::post('/{officialTimeRequest}/decide', [OfficialTimeApprovalController::class, 'decide'])->name('decide');
        Route::post('/{officialTimeRequest}/return', [OfficialTimeApprovalController::class, 'returnForRevision'])->name('return');
    });

    Route::prefix('travel-order/approvals')->name('travel-order.approvals.')->group(function () {
        Route::get('/', [TravelOrderApprovalController::class, 'index'])->name('index');
        Route::get('/history', [TravelOrderApprovalController::class, 'history'])->name('history');
        Route::get('/{travelOrder}', [TravelOrderApprovalController::class, 'show'])->name('show');
        Route::post('/{travelOrder}/decide', [TravelOrderApprovalController::class, 'decide'])->name('decide');
        Route::get('/{travelOrder}/pdf', [TravelOrderApprovalController::class, 'pdf'])->name('pdf');
        Route::get('/{travelOrder}/print', [TravelOrderApprovalController::class, 'print'])->name('print');
    });

    Route::prefix('leave/approvals')->name('leave.approvals.')->group(function () {
        Route::get('/', [LeaveApprovalController::class, 'index'])->name('index');
        Route::get('/history', [LeaveApprovalController::class, 'history'])->name('history');
        Route::get('/{application}', [LeaveApprovalController::class, 'show'])->name('show');
        Route::post('/{application}/decide', [LeaveApprovalController::class, 'decide'])->name('decide');
        Route::get('/{application}/pdf', [LeaveApprovalController::class, 'pdf'])->name('pdf');
        Route::get('/{application}/print', [LeaveApprovalController::class, 'print'])->name('print');
    });

    Route::middleware('role:admin,supervisor')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/live', [AdminDashboardController::class, 'live'])->name('dashboard.live');

        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [EmployeeController::class, 'create'])->middleware('role:admin')->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->middleware('role:admin')->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->middleware('role:admin')->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('role:admin')->name('employees.update');
        Route::post('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->middleware('role:admin')->name('employees.deactivate');
        Route::post('/employees/{employee}/activate', [EmployeeController::class, 'activate'])->middleware('role:admin')->name('employees.activate');
        Route::get('/employees/{employee}/qr', [EmployeeQrController::class, 'show'])->middleware('role:admin')->name('employees.qr');
        Route::post('/employees/{employee}/qr/generate', [EmployeeQrController::class, 'generate'])->middleware('role:admin')->name('employees.qr.generate');
        Route::post('/employees/{employee}/qr/regenerate', [EmployeeQrController::class, 'regenerate'])->middleware('role:admin')->name('employees.qr.regenerate');
        Route::post('/employees/{employee}/qr/disable', [EmployeeQrController::class, 'disable'])->middleware('role:admin')->name('employees.qr.disable');
        Route::post('/employees/{employee}/qr/enable', [EmployeeQrController::class, 'enable'])->middleware('role:admin')->name('employees.qr.enable');
        Route::get('/employees/{employee}/qr/print', [EmployeeQrController::class, 'print'])->middleware('role:admin')->name('employees.qr.print');

        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->middleware('role:admin')->name('departments.store');
        Route::get('/departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('role:admin')->name('departments.update');
        Route::post('/departments/{department}/deactivate', [DepartmentController::class, 'deactivate'])->middleware('role:admin')->name('departments.deactivate');

        Route::get('/designations', [DesignationController::class, 'index'])->name('designations.index');
        Route::middleware('role:admin')->group(function () {
            Route::get('/designations/create', [DesignationController::class, 'create'])->name('designations.create');
            Route::post('/designations', [DesignationController::class, 'store'])->name('designations.store');
            Route::get('/designations/{designation}/edit', [DesignationController::class, 'edit'])->name('designations.edit');
            Route::put('/designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        });
        Route::get('/designations/{designation}', [DesignationController::class, 'show'])->name('designations.show');

        Route::middleware('role:admin')->group(function () {
            Route::get('/attendance-stations', [AttendanceStationController::class, 'index'])->name('stations.index');
            Route::get('/attendance-stations/create', [AttendanceStationController::class, 'create'])->name('stations.create');
            Route::post('/attendance-stations', [AttendanceStationController::class, 'store'])->name('stations.store');
            Route::get('/station-monitoring', [StationMonitoringController::class, 'index'])->name('stations.monitoring');
            Route::get('/attendance-stations/{station}', [AttendanceStationController::class, 'show'])->name('stations.show');
            Route::get('/attendance-stations/{station}/edit', [AttendanceStationController::class, 'edit'])->name('stations.edit');
            Route::put('/attendance-stations/{station}', [AttendanceStationController::class, 'update'])->name('stations.update');
            Route::delete('/attendance-stations/{station}', [AttendanceStationController::class, 'destroy'])->name('stations.destroy');
            Route::post('/attendance-stations/{station}/activate', [AttendanceStationController::class, 'activate'])->name('stations.activate');
            Route::post('/attendance-stations/{station}/deactivate', [AttendanceStationController::class, 'deactivate'])->name('stations.deactivate');
            Route::post('/attendance-stations/{station}/lock', [AttendanceStationController::class, 'lock'])->name('stations.lock');
            Route::post('/attendance-stations/{station}/unlock', [AttendanceStationController::class, 'unlock'])->name('stations.unlock');
            Route::post('/attendance-stations/{station}/reset-password', [AttendanceStationController::class, 'resetPassword'])->name('stations.reset-password');
            Route::post('/attendance-stations/{station}/unbind', [AttendanceStationController::class, 'unbind'])->name('stations.unbind');
            Route::get('/attendance-stations/{station}/activity', [AttendanceStationController::class, 'activity'])->name('stations.activity');
            Route::get('/attendance-stations/{station}/attendance', [AttendanceStationController::class, 'attendance'])->name('stations.attendance');
        });

        Route::get('/attendance', [AdminDtrController::class, 'index'])->name('attendance.index');
        Route::get('/attendance-corrections', [AdminAttendanceCorrectionController::class, 'index'])->name('attendance-corrections.index');
        Route::get('/attendance-corrections/{correction}', [AdminAttendanceCorrectionController::class, 'show'])->name('attendance-corrections.show');
        Route::post('/attendance-corrections/{correction}/review', [AdminAttendanceCorrectionController::class, 'review'])->middleware('role:admin')->name('attendance-corrections.review');

        Route::get('/dtr', [AdminDtrController::class, 'index'])->name('dtr.index');
        Route::get('/dtr/monthly', [AdminDtrController::class, 'monthly'])->name('dtr.monthly');
        Route::get('/dtr/create', [AdminDtrController::class, 'create'])->middleware('role:admin')->name('dtr.create');
        Route::post('/dtr', [AdminDtrController::class, 'store'])->middleware('role:admin')->name('dtr.store');
        Route::get('/dtr/{attendance}', [AdminDtrController::class, 'show'])->name('dtr.show');
        Route::get('/dtr/{attendance}/edit', [AdminDtrController::class, 'edit'])->middleware('role:admin')->name('dtr.edit');
        Route::put('/dtr/{attendance}', [AdminDtrController::class, 'update'])->middleware('role:admin')->name('dtr.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
        Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('/reports/late', [ReportController::class, 'late'])->name('reports.late');
        Route::get('/reports/absences', [ReportController::class, 'absences'])->name('reports.absences');
        Route::get('/reports/overtime', [ReportController::class, 'overtime'])->name('reports.overtime');
        Route::get('/reports/undertime', [ReportController::class, 'undertime'])->name('reports.undertime');

        Route::get('/calendar', [AdminCalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/live', [AdminCalendarController::class, 'live'])->name('calendar.live');
        Route::get('/calendar/events', [CalendarEventController::class, 'index'])->name('calendar.events.index');
        Route::get('/calendar/events/create', [CalendarEventController::class, 'create'])->middleware('role:admin')->name('calendar.events.create');
        Route::post('/calendar/events', [CalendarEventController::class, 'store'])->middleware('role:admin')->name('calendar.events.store');
        Route::get('/calendar/events/{event}', [CalendarEventController::class, 'show'])->name('calendar.events.show');
        Route::get('/calendar/events/{event}/edit', [CalendarEventController::class, 'edit'])->middleware('role:admin')->name('calendar.events.edit');
        Route::put('/calendar/events/{event}', [CalendarEventController::class, 'update'])->middleware('role:admin')->name('calendar.events.update');
        Route::delete('/calendar/events/{event}', [CalendarEventController::class, 'destroy'])->middleware('role:admin')->name('calendar.events.destroy');

        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('/schedules', [ScheduleController::class, 'store'])->middleware('role:admin')->name('schedules.store');
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('role:admin')->name('schedules.update');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin')->name('audit.index');

        Route::get('/email-notifications/preview', [EmailNotificationController::class, 'preview'])->middleware('role:admin')->name('email-notifications.preview');
        Route::post('/email-notifications/test', [EmailNotificationController::class, 'sendTest'])->middleware('role:admin')->name('email-notifications.test');

        Route::get('/settings', [SettingController::class, 'index'])->middleware('role:admin')->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->middleware('role:admin')->name('settings.update');
        Route::post('/settings/holidays', [SettingController::class, 'storeHoliday'])->middleware('role:admin')->name('settings.holidays.store');
        Route::delete('/settings/holidays/{holiday}', [SettingController::class, 'destroyHoliday'])->middleware('role:admin')->name('settings.holidays.destroy');
        Route::post('/settings/admins', [AdminAccountController::class, 'store'])->middleware('role:admin')->name('settings.admins.store');
        Route::get('/settings/admins/{admin}/edit', [AdminAccountController::class, 'edit'])->middleware('role:admin')->name('settings.admins.edit');
        Route::put('/settings/admins/{admin}', [AdminAccountController::class, 'update'])->middleware('role:admin')->name('settings.admins.update');
        Route::delete('/settings/admins/{admin}', [AdminAccountController::class, 'destroy'])->middleware('role:admin')->name('settings.admins.destroy');
        Route::get('/settings/approval-workflow/employees/search', [ApprovalWorkflowController::class, 'searchEmployees'])->middleware('role:admin')->name('settings.approval-workflow.employees.search');
        Route::put('/settings/approval-workflow/{transactionType}', [ApprovalWorkflowController::class, 'update'])->middleware('role:admin')->name('settings.approval-workflow.update');
        Route::get('/settings/approval-workflow/{transactionType}/history', [ApprovalWorkflowController::class, 'history'])->middleware('role:admin')->name('settings.approval-workflow.history');
        Route::post('/settings/official-time-types', [OfficialTimeTypeController::class, 'store'])->middleware('role:admin')->name('settings.official-time-types.store');
        Route::put('/settings/official-time-types/{officialTimeType}', [OfficialTimeTypeController::class, 'update'])->middleware('role:admin')->name('settings.official-time-types.update');

        Route::get('/official-time', [AdminOfficialTimeController::class, 'index'])->name('official-time.index');
        Route::get('/official-time/{officialTimeRequest}', [AdminOfficialTimeController::class, 'show'])->name('official-time.show');
        Route::get('/official-time/{officialTimeRequest}/attachment', [EmployeeOfficialTimeController::class, 'attachment'])->name('official-time.attachment');

        Route::get('/leave', [AdminLeaveApplicationController::class, 'index'])->name('leave.index');
        Route::get('/leave/configuration', fn () => redirect()->route('admin.settings.index')->withFragment('approval-workflow'))->middleware('role:admin')->name('leave.workflow');
        Route::get('/leave/configuration/employees/search', [LeaveWorkflowController::class, 'searchEmployees'])->middleware('role:admin')->name('leave.workflow.employees.search');
        Route::get('/leave/configuration/{department}', [LeaveWorkflowController::class, 'show'])->middleware('role:admin')->name('leave.workflow.show');
        Route::put('/leave/configuration/{department}', [LeaveWorkflowController::class, 'update'])->middleware('role:admin')->name('leave.workflow.update');
        Route::post('/leave/configuration/{department}/activate', [LeaveWorkflowController::class, 'activate'])->middleware('role:admin')->name('leave.workflow.activate');
        Route::post('/leave/configuration/{department}/deactivate', [LeaveWorkflowController::class, 'deactivate'])->middleware('role:admin')->name('leave.workflow.deactivate');
        Route::get('/leave/configuration/{department}/history', [LeaveWorkflowController::class, 'history'])->middleware('role:admin')->name('leave.workflow.history');
        Route::get('/leave/entitlements', [LeaveEntitlementController::class, 'index'])->middleware('role:admin')->name('leave.entitlements');
        Route::get('/leave/entitlements/policy', [LeavePolicyController::class, 'edit'])->middleware('role:admin')->name('leave.policy');
        Route::put('/leave/entitlements/policy', [LeavePolicyController::class, 'update'])->middleware('role:admin')->name('leave.policy.update');
        Route::get('/leave/entitlements/{employee}', [LeaveEntitlementController::class, 'show'])->middleware('role:admin')->name('leave.entitlements.show');
        Route::get('/leave/entitlements/{employee}/edit', [LeaveEntitlementController::class, 'edit'])->middleware('role:admin')->name('leave.entitlements.edit');
        Route::post('/leave/entitlements/{employee}/adjustments/preview', [LeaveEntitlementController::class, 'previewAdjustment'])->middleware('role:admin')->name('leave.entitlements.adjustments.preview');
        Route::post('/leave/entitlements/{employee}/adjustments', [LeaveEntitlementController::class, 'storeAdjustment'])->middleware('role:admin')->name('leave.entitlements.adjustments.store');
        Route::get('/leave/entitlements/{employee}/adjustments', [LeaveEntitlementController::class, 'adjustmentHistory'])->name('leave.entitlements.adjustments');
        Route::get('/leave/entitlements/{employee}/leave-history', [LeaveEntitlementController::class, 'leaveHistory'])->name('leave.entitlements.leave-history');
        Route::get('/leave/reports', [LeaveReportController::class, 'index'])->name('leave.reports');
        Route::get('/leave/{application}', [AdminLeaveApplicationController::class, 'show'])->name('leave.show');
        Route::post('/leave/{application}/hr', [AdminLeaveApplicationController::class, 'processHr'])->name('leave.hr');
        Route::get('/leave/{application}/pdf', [AdminLeaveApplicationController::class, 'pdf'])->name('leave.pdf');
        Route::get('/leave/{application}/print', [AdminLeaveApplicationController::class, 'print'])->name('leave.print');

        Route::get('/travel-orders', [AdminTravelOrderController::class, 'index'])->middleware('role:admin')->name('travel-orders.index');
        Route::get('/travel-orders/{travelOrder}', [AdminTravelOrderController::class, 'show'])->middleware('role:admin')->name('travel-orders.show');
        Route::get('/travel-orders/{travelOrder}/edit', [AdminTravelOrderController::class, 'edit'])->middleware('role:admin')->name('travel-orders.edit');
        Route::put('/travel-orders/{travelOrder}/approved', [AdminTravelOrderController::class, 'updateApproved'])->middleware('role:admin')->name('travel-orders.update-approved');
        Route::post('/travel-orders/{travelOrder}/cancel', [AdminTravelOrderController::class, 'cancelApproved'])->middleware('role:admin')->name('travel-orders.cancel');
        Route::get('/travel-orders/{travelOrder}/pdf', [AdminTravelOrderController::class, 'pdf'])->middleware('role:admin')->name('travel-orders.pdf');
        Route::get('/travel-orders/{travelOrder}/print', [AdminTravelOrderController::class, 'print'])->middleware('role:admin')->name('travel-orders.print');

        Route::prefix('payroll')->name('payroll.')->group(function () {
            Route::get('/', [PayrollDashboardController::class, 'index'])->name('dashboard');
            Route::get('/periods', [PayrollPeriodController::class, 'index'])->name('periods.index');
            Route::get('/periods/{period}', [PayrollPeriodController::class, 'show'])->name('periods.show');
            Route::get('/periods/{period}/register', [PayrollRegisterController::class, 'index'])->name('register.index');
            Route::get('/periods/{period}/register/export', [PayrollRegisterController::class, 'export'])->name('register.export');
            Route::get('/payroll-employees/{payrollEmployee}', [PayrollEmployeeController::class, 'show'])->name('employees.payroll.show');
            Route::get('/payroll-employees/{payrollEmployee}/pdf', [PayrollEmployeeController::class, 'pdf'])->name('employees.payroll.pdf');
            Route::get('/payroll-employees/{payrollEmployee}/print', [PayrollEmployeeController::class, 'print'])->name('employees.payroll.print');
            Route::get('/periods/{period}/overtime', [PayrollOvertimeRequestController::class, 'index'])->name('overtime.index');

            Route::middleware('role:admin')->group(function () {
                Route::get('/designations', fn () => redirect()->route('admin.designations.index'));
                Route::get('/periods/create', [PayrollPeriodController::class, 'create'])->name('periods.create');
                Route::post('/periods', [PayrollPeriodController::class, 'store'])->name('periods.store');
                Route::post('/periods/{period}/compute', [PayrollPeriodController::class, 'compute'])->name('periods.compute');
                Route::post('/periods/{period}/compute-payroll', [PayrollPeriodController::class, 'computePayroll'])->name('periods.compute-payroll');
                Route::put('/periods/{period}/status', [PayrollPeriodController::class, 'updateStatus'])->name('periods.status');
                Route::post('/periods/{period}/notify-payslips', [PayrollPeriodController::class, 'notifyPayslips'])->name('periods.notify-payslips');
                Route::get('/settings', [PayrollSettingsController::class, 'index'])->name('settings.index');
                Route::post('/settings/general', [PayrollSettingsController::class, 'updateGeneral'])->name('settings.general');
                Route::post('/settings/statutory', [PayrollSettingsController::class, 'updateStatutory'])->name('settings.statutory');
                Route::post('/settings/late-deductions', [PayrollSettingsController::class, 'updateLateDeductions'])->name('settings.late-deductions');
                Route::post('/settings/undertime-deductions', [PayrollSettingsController::class, 'updateUndertimeDeductions'])->name('settings.undertime-deductions');
                Route::put('/settings/deduction-types/{deductionType}', [PayrollSettingsController::class, 'updateDeductionType'])->name('settings.deduction-types.update');
                Route::put('/settings/rules/{rule}', [PayrollSettingsController::class, 'updateRule'])->name('settings.rules.update');
                Route::post('/overtime/{overtimeRequest}/decide', [PayrollOvertimeRequestController::class, 'decide'])->name('overtime.decide');
                Route::get('/periods/{period}/adjustments', [PayrollAdjustmentController::class, 'index'])->name('adjustments.index');
                Route::get('/periods/{period}/adjustments/create', [PayrollAdjustmentController::class, 'create'])->name('adjustments.create');
                Route::post('/periods/{period}/adjustments', [PayrollAdjustmentController::class, 'store'])->name('adjustments.store');
                Route::get('/employees/{employee}/salary', [PayrollEmployeeSalaryController::class, 'index'])->name('employees.salary.index');
                Route::post('/employees/{employee}/salary', [PayrollEmployeeSalaryController::class, 'store'])->name('employees.salary.store');
                Route::post('/employees/{employee}/benefits', [PayrollEmployeeSalaryController::class, 'storeBenefit'])->name('employees.benefits.store');
                Route::post('/employees/{employee}/deductions', [PayrollEmployeeSalaryController::class, 'storeDeduction'])->name('employees.deductions.store');
                Route::put('/payroll-employees/{payrollEmployee}/total-basic-pay', [PayrollEmployeeController::class, 'updateTotalBasicPay'])->name('employees.payroll.total-basic-pay');
            });
        });
    });
});
