<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeScheduleController;
use App\Http\Controllers\EnrollmentController;
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
