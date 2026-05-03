<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ElectronicInvoice\AuditController as EiAuditController;
use App\Http\Controllers\ElectronicInvoice\DashboardController as EiDashboardController;
use App\Http\Controllers\ElectronicInvoice\IssuedController as EiIssuedController;
use App\Http\Controllers\ElectronicInvoice\ReceivedController as EiReceivedController;
use App\Http\Controllers\ElectronicInvoice\SettingsController as EiSettingsController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeScheduleController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\Payroll\CommissionRuleController;
use App\Http\Controllers\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\Payroll\PayrollDashboardController;
use App\Http\Controllers\Payroll\PayrollEmployeeController;
use App\Http\Controllers\Payroll\PayrollPeriodController;
use App\Http\Controllers\Payroll\PayrollRecordController;
use App\Http\Controllers\Payroll\PayrollReportController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\PosShiftController;
use App\Http\Controllers\Pos\PosTicketController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicCourseController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('homepage');
})->name('home');

// Routes accessible without business middleware (super_admin + dashboard)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Businesses Management (super_admin only via policy)
    Route::resource('businesses', BusinessController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);

    // Users Management (super_admin + business_admin via policy)
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

// Routes requiring business context
Route::middleware(['auth', 'verified', 'business'])->group(function () {
    // Business Management (own business for business_admin)
    Route::get('/business', [BusinessController::class, 'showOwn'])->name('business.show');
    Route::get('/business/edit', [BusinessController::class, 'editOwn'])->name('business.edit');
    Route::put('/business', [BusinessController::class, 'updateOwn'])->name('business.update');

    // Services Management
    Route::resource('services', ServiceController::class);

    // Service Categories Management
    Route::resource('service-categories', ServiceCategoryController::class)->except(['show']);

    // Promotions Management
    Route::resource('promotions', PromotionController::class)->except(['show']);

    // Employees Management
    Route::resource('employees', EmployeeController::class);

    // Employee Schedules Management (Nested Resource)
    Route::get('/employees/{employee}/schedules', [EmployeeScheduleController::class, 'index'])->name('employees.schedules.index');
    Route::get('/employees/{employee}/schedules/edit', [EmployeeScheduleController::class, 'edit'])->name('employees.schedules.edit');
    Route::put('/employees/{employee}/schedules', [EmployeeScheduleController::class, 'update'])->name('employees.schedules.update');
    Route::delete('/employees/{employee}/schedules', [EmployeeScheduleController::class, 'destroy'])->name('employees.schedules.destroy');

    // Appointments Management
    Route::get('/appointments/availability', [AppointmentController::class, 'availability'])->name('appointments.availability');
    Route::resource('appointments', AppointmentController::class);

    // Clients Management
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');

    // QR Codes (admin-only UI)
    Route::get('/qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
    Route::get('/qr-codes/create', [QrCodeController::class, 'create'])->name('qr-codes.create');
    Route::post('/qr-codes', [QrCodeController::class, 'store'])->name('qr-codes.store');
    Route::get('/qr-codes/{qrCode}', [QrCodeController::class, 'view'])->name('qr-codes.show');

    // Payroll Dashboard
    Route::get('/payroll/dashboard', [PayrollDashboardController::class, 'index'])->name('payroll.dashboard');

    // Payroll Management (read)
    Route::get('/payroll/periods', [PayrollPeriodController::class, 'index'])->name('payroll.periods.index');
    Route::get('/payroll/periods/{period}', [PayrollPeriodController::class, 'show'])->name('payroll.periods.show');
    Route::get('/payroll/periods/{period}/export', [PayrollPeriodController::class, 'export'])->name('payroll.periods.export');
    Route::get('/payroll/periods/{period}/employees/{employee}', [PayrollPeriodController::class, 'employee'])->name('payroll.periods.employee');

    // Payroll Employee Historical
    Route::get('/payroll/employees/{employee}', [PayrollEmployeeController::class, 'show'])->name('payroll.employees.show');
    Route::get('/payroll/employees/{employee}/export', [PayrollEmployeeController::class, 'export'])->name('payroll.employees.export');
    Route::patch('/payroll/employees/{employee}/base-salary', [PayrollEmployeeController::class, 'updateBaseSalary'])->name('payroll.employees.base-salary');

    // Payroll Reports
    Route::get('/payroll/reports/by-service', [PayrollReportController::class, 'byService'])->name('payroll.reports.by-service');

    // Payroll Adjustments Index
    Route::get('/payroll/adjustments', [PayrollAdjustmentController::class, 'index'])->name('payroll.adjustments.index');

    // Commission Rules CRUD
    Route::get('/payroll/commission-rules', [CommissionRuleController::class, 'index'])->name('payroll.commission-rules.index');
    Route::post('/payroll/commission-rules', [CommissionRuleController::class, 'store'])->name('payroll.commission-rules.store');
    Route::put('/payroll/commission-rules/{rule}', [CommissionRuleController::class, 'update'])->name('payroll.commission-rules.update');
    Route::delete('/payroll/commission-rules/{rule}', [CommissionRuleController::class, 'destroy'])->name('payroll.commission-rules.destroy');

    // Payroll Management (mutations — rate limited)
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/payroll/periods', [PayrollPeriodController::class, 'store'])->name('payroll.periods.store');
        Route::post('/payroll/periods/{period}/generate', [PayrollPeriodController::class, 'generate'])->name('payroll.periods.generate');
        Route::post('/payroll/periods/{period}/approve', [PayrollPeriodController::class, 'approve'])->name('payroll.periods.approve');
        Route::post('/payroll/records/{record}/mark-paid', [PayrollRecordController::class, 'markPaid'])->name('payroll.records.mark-paid');
        Route::post('/payroll/records/{record}/void', [PayrollRecordController::class, 'void'])->name('payroll.records.void');
        Route::post('/payroll/periods/{period}/adjustments', [PayrollAdjustmentController::class, 'store'])->name('payroll.adjustments.store');
    });

    // Electronic Invoice Module
    Route::prefix('admin/electronic-invoice')->name('electronic-invoice.')->group(function () {
        Route::get('/dashboard', [EiDashboardController::class, 'index'])->name('dashboard');

        // Issued e-CFs
        Route::get('/issued', [EiIssuedController::class, 'index'])->name('issued.index');
        Route::get('/issued/create', [EiIssuedController::class, 'create'])->name('issued.create');
        Route::get('/issued/{ecf}', [EiIssuedController::class, 'show'])->name('issued.show');

        // Received e-CFs
        Route::get('/received', [EiReceivedController::class, 'index'])->name('received.index');
        Route::get('/received/{received}', [EiReceivedController::class, 'show'])->name('received.show');

        // Settings (read-only)
        Route::get('/settings', [EiSettingsController::class, 'show'])->name('settings');

        // Audit log
        Route::get('/audit', [EiAuditController::class, 'index'])->name('audit.index');

        // FE mutation endpoints — rate limited (30 req/min)
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/issued', [EiIssuedController::class, 'store'])->name('issued.store');
            Route::post('/issued/{ecf}/credit-note', [EiIssuedController::class, 'creditNote'])->name('issued.credit-note');
            Route::post('/issued/{ecf}/resend', [EiIssuedController::class, 'resend'])->name('issued.resend');
            Route::post('/issued/{ecf}/register-manual-void', [EiIssuedController::class, 'registerManualVoid'])->name('issued.register-manual-void');
            Route::post('/received/{received}/approve', [EiReceivedController::class, 'approve'])->name('received.approve');
            Route::post('/received/{received}/reject', [EiReceivedController::class, 'reject'])->name('received.reject');
            Route::put('/settings', [EiSettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/test-connectivity', [EiSettingsController::class, 'testConnectivity'])->name('settings.test-connectivity');
            Route::post('/settings/upload-certificate', [EiSettingsController::class, 'uploadCertificate'])->name('settings.upload-certificate');
        });
    });

    // POS — Punto de Venta
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/tickets', [PosTicketController::class, 'index'])->name('pos.tickets.index');
    Route::get('/pos/tickets/{ticket}', [PosTicketController::class, 'show'])->name('pos.tickets.show');
    Route::get('/pos/shift-close', [PosShiftController::class, 'create'])->name('pos.shift.create');

    // POS ticket mutations — rate limited (60 req/min, high volume at counter)
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/pos/tickets', [PosTicketController::class, 'store'])->name('pos.tickets.store');
        Route::post('/pos/tickets/{ticket}/void', [PosTicketController::class, 'void'])->name('pos.tickets.void');
    });

    // POS shift close — rate limited (5 req/min, rare operation)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/pos/shift-close', [PosShiftController::class, 'store'])->name('pos.shift.store');
    });

    // Courses
    Route::resource('courses', CourseController::class);
    Route::get('/courses/{course}/enrollments', [EnrollmentController::class, 'index'])->name('courses.enrollments.index');
    Route::patch('/enrollments/{enrollment}/status', [EnrollmentController::class, 'updateStatus'])->name('enrollments.update-status');
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    Route::get('/courses/{course}/enrollments/export', [EnrollmentController::class, 'export'])->name('courses.enrollments.export');
});

// Public course pages (no auth required, SEO)
Route::get('/{business:slug}/courses', [PublicCourseController::class, 'index'])->name('public.courses.index');
Route::get('/{business:slug}/courses/{courseSlug}', [PublicCourseController::class, 'show'])->name('public.courses.show');

require __DIR__.'/settings.php';
