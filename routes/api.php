<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GoogleOAuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\QrScanController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\VisitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    // Lead creation (public, for web app)
    Route::post('/leads', [LeadController::class, 'store']);
    Route::post('/leads/with-appointment', [LeadController::class, 'storeWithAppointment']);

    // Business search (MUST be before {invitationCode} to avoid route conflict)
    Route::get('/businesses/search', [BusinessController::class, 'search']);

    // Business Discovery
    Route::get('/businesses/{invitationCode}', [BusinessController::class, 'showByInvitationCode']);
    Route::get('/businesses/{businessId}/employees', [BusinessController::class, 'employees']);
    Route::get('/businesses/{businessId}/services/{serviceId}/employees', [BusinessController::class, 'serviceEmployees']);

    // Service Catalog (public, by business)
    Route::get('/businesses/{businessId}/services', [ServiceController::class, 'index']);
    Route::get('/businesses/{businessId}/services/{serviceId}', [ServiceController::class, 'show']);

    // Service Categories (public, by business)
    Route::get('/businesses/{businessId}/categories', [ServiceCategoryController::class, 'index']);
    Route::get('/businesses/{businessId}/categories/{categoryId}', [ServiceCategoryController::class, 'show']);

    // Promotions (public, by business)
    Route::get('/businesses/{businessId}/promotions', [PromotionController::class, 'index']);

    // Availability (public, by business)
    Route::get('/businesses/{businessId}/availability', [BusinessController::class, 'availability']);

    // Services (standalone)
    Route::get('/services/{serviceId}/employees', [ServiceController::class, 'employees']);

    // Employees
    Route::get('/employees/{employeeId}', [EmployeeController::class, 'show']);

    // QR code validation (can be checked without auth)
    Route::post('/visits/check-qr', [VisitController::class, 'checkQR']);
});

// Protected routes (require Sanctum authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/push-token', [AuthController::class, 'updatePushToken']);

    // Appointments
    Route::get('/appointments/availability', [AppointmentController::class, 'availability']);
    Route::apiResource('appointments', AppointmentController::class)->except(['update']);

    // Visits (QR verification)
    Route::post('/visits/verify', [VisitController::class, 'verifyQR']);

    // Google OAuth for employees
    Route::get('/google/oauth/redirect', [GoogleOAuthController::class, 'redirect']);
    Route::get('/google/oauth/callback', [GoogleOAuthController::class, 'handleCallback']);

    // QR Codes (admin only)
    Route::get('/qr-codes', [QrCodeController::class, 'index']);
    Route::post('/qr-codes', [QrCodeController::class, 'store']);
    Route::get('/qr-codes/{qrCode}', [QrCodeController::class, 'show']);
    Route::put('/qr-codes/{qrCode}', [QrCodeController::class, 'update']);
    Route::patch('/qr-codes/{qrCode}', [QrCodeController::class, 'update']);
    Route::delete('/qr-codes/{qrCode}', [QrCodeController::class, 'destroy']);
    Route::get('/qr-codes/{qrCode}/image', [QrCodeController::class, 'image']);

    // QR Scans (clients)
    Route::post('/qr/scan', [QrScanController::class, 'store']);
    Route::post('/visits/verify-qr', [QrScanController::class, 'store']);

    // Loyalty (client)
    Route::get('/loyalty/progress', [LoyaltyController::class, 'progress']);
    Route::get('/loyalty/stamps', [LoyaltyController::class, 'stamps']);
});
