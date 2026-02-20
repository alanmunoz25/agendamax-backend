<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send reminder emails 24 hours before appointments
Schedule::call(function () {
    $emailService = app(EmailNotificationService::class);

    $appointments = Appointment::where('status', 'confirmed')
        ->whereBetween('scheduled_at', [
            now()->addHours(23)->addMinutes(50),
            now()->addHours(24)->addMinutes(10),
        ])
        ->get();

    foreach ($appointments as $appointment) {
        try {
            $emailService->sendAppointmentReminder($appointment);
        } catch (\Exception $e) {
            \Log::error('Failed to send 24h reminder', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->hourly()->name('send-24h-reminders')->withoutOverlapping();

// Send reminder emails 2 hours before appointments
Schedule::call(function () {
    $emailService = app(EmailNotificationService::class);

    $appointments = Appointment::where('status', 'confirmed')
        ->whereBetween('scheduled_at', [
            now()->addHours(1)->addMinutes(50),
            now()->addHours(2)->addMinutes(10),
        ])
        ->get();

    foreach ($appointments as $appointment) {
        try {
            $emailService->sendAppointmentReminder($appointment);
        } catch (\Exception $e) {
            \Log::error('Failed to send 2h reminder', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
})->everyTenMinutes()->name('send-2h-reminders')->withoutOverlapping();

// Clean up expired QR codes (older than 24 hours)
Schedule::call(function () {
    // QR codes are stored in appointments table as qr_code field
    // Clear QR codes that are older than 24 hours
    $clearedCount = Appointment::whereNotNull('qr_code')
        ->where('scheduled_at', '<', now()->subHours(24))
        ->update(['qr_code' => null]);

    \Log::info('Expired QR codes cleaned up', [
        'count' => $clearedCount,
    ]);
})->daily()->at('03:00')->name('cleanup-expired-qr')->withoutOverlapping();
