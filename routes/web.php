<?php

use App\Http\Controllers\Admin\AttendanceStationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DtrController as AdminDtrController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeQrController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StationMonitoringController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClockController;
use App\Http\Controllers\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\DtrController as EmployeeDtrController;
use App\Http\Controllers\Employee\QrCodeController as EmployeeQrCodeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
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
});

Route::middleware(['auth', 'account.active', 'password.changed'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/home', function () {
        return auth()->user()->isManagement()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('employee.dashboard');
    })->name('home');

    Route::get('/server-time', [ClockController::class, 'serverTime'])->name('server-time');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('profile.password');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::post('/attendance/time-in', [ClockController::class, 'timeIn'])->middleware('throttle:clock')->name('attendance.time-in');
    Route::post('/attendance/time-out', [ClockController::class, 'timeOut'])->middleware('throttle:clock')->name('attendance.time-out');
    Route::get('/attendance/today', [ClockController::class, 'today'])->name('attendance.today');

    Route::prefix('employee')->name('employee.')->group(function () {
        Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
        Route::get('/attendance', [EmployeeAttendanceController::class, 'index'])->name('attendance');
        Route::get('/dtr', [EmployeeDtrController::class, 'index'])->name('dtr');
        Route::get('/dtr/export', [EmployeeDtrController::class, 'export'])->name('dtr.export');
        Route::get('/dtr/print', [EmployeeDtrController::class, 'print'])->name('dtr.print');
        Route::get('/dtr/{employee}', [EmployeeDtrController::class, 'show'])->name('dtr.show');
        Route::get('/qr-code', [EmployeeQrCodeController::class, 'show'])->name('qr');
        Route::get('/qr-code/print', [EmployeeQrCodeController::class, 'print'])->name('qr.print');
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

        Route::middleware('role:admin')->group(function () {
            Route::get('/attendance-stations', [AttendanceStationController::class, 'index'])->name('stations.index');
            Route::get('/attendance-stations/create', [AttendanceStationController::class, 'create'])->name('stations.create');
            Route::post('/attendance-stations', [AttendanceStationController::class, 'store'])->name('stations.store');
            Route::get('/station-monitoring', [StationMonitoringController::class, 'index'])->name('stations.monitoring');
            Route::get('/attendance-stations/{station}', [AttendanceStationController::class, 'show'])->name('stations.show');
            Route::get('/attendance-stations/{station}/edit', [AttendanceStationController::class, 'edit'])->name('stations.edit');
            Route::put('/attendance-stations/{station}', [AttendanceStationController::class, 'update'])->name('stations.update');
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

        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('/schedules', [ScheduleController::class, 'store'])->middleware('role:admin')->name('schedules.store');
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('role:admin')->name('schedules.update');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin')->name('audit.index');

        Route::get('/settings', [SettingController::class, 'index'])->middleware('role:admin')->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->middleware('role:admin')->name('settings.update');
        Route::post('/settings/holidays', [SettingController::class, 'storeHoliday'])->middleware('role:admin')->name('settings.holidays.store');
        Route::delete('/settings/holidays/{holiday}', [SettingController::class, 'destroyHoliday'])->middleware('role:admin')->name('settings.holidays.destroy');
    });
});
