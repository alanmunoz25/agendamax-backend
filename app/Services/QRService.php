<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class QRService
{
    /**
     * Generate a QR code payload for an appointment visit.
     *
     * Employee generates this QR code to be scanned by the client.
     *
     * @return string Encrypted QR code payload
     *
     * @throws \Exception
     */
    public function generateVisitQR(int $appointmentId): string
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // Ensure appointment belongs to authenticated user's business
        if ($appointment->business_id !== Auth::user()->business_id) {
            throw new \Exception('Unauthorized access to appointment');
        }

        // Only generate QR for confirmed appointments
        if ($appointment->status !== 'confirmed') {
            throw new \Exception('Can only generate QR for confirmed appointments');
        }

        // Create payload with appointment data
        $payload = [
            'appointment_id' => $appointment->id,
            'business_id' => $appointment->business_id,
            'employee_id' => $appointment->employee_id,
            'client_id' => $appointment->client_id,
            'expires_at' => Carbon::now()->addHours(24)->timestamp,
            'generated_at' => Carbon::now()->timestamp,
        ];

        // Encrypt and return the payload
        return Crypt::encrypt($payload);
    }

    /**
     * Verify a QR code and create a visit record.
     *
     * Client scans the QR code to verify their visit.
     *
     * @throws \Exception
     */
    public function verifyQR(string $qrCode): Visit
    {
        try {
            // Decrypt the QR code payload
            $payload = Crypt::decrypt($qrCode);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw new \Exception('Invalid or tampered QR code');
        }

        // Validate payload structure
        if (! $this->isValidPayload($payload)) {
            throw new \Exception('Invalid QR code format');
        }

        // Check expiration (24 hours)
        if (Carbon::now()->timestamp > $payload['expires_at']) {
            throw new \Exception('QR code has expired');
        }

        // Get appointment
        $appointment = Appointment::find($payload['appointment_id']);

        if (! $appointment) {
            throw new \Exception('Appointment not found');
        }

        // Verify appointment belongs to the correct business
        if ($appointment->business_id !== $payload['business_id']) {
            throw new \Exception('Invalid business association');
        }

        // Verify client matches (if authenticated)
        if (Auth::check() && $appointment->client_id !== Auth::id()) {
            throw new \Exception('QR code is not for this client');
        }

        // Check for one-time use - prevent duplicate visits
        if ($appointment->visit()->exists()) {
            throw new \Exception('QR code has already been used');
        }

        // Verify appointment is in valid state for visit
        if (! in_array($appointment->status, ['confirmed', 'pending'])) {
            throw new \Exception('Appointment is not in valid state for check-in');
        }

        // Create visit record
        $visit = Visit::create([
            'business_id' => $appointment->business_id,
            'client_id' => $appointment->client_id,
            'employee_id' => $appointment->employee_id,
            'appointment_id' => $appointment->id,
            'verified_at' => Carbon::now(),
            'qr_code' => $qrCode,
            'stamp_awarded' => false, // Will be awarded when appointment is completed
        ]);

        // Update appointment status to confirmed if it was pending
        if ($appointment->status === 'pending') {
            $appointment->update(['status' => 'confirmed']);
        }

        return $visit->load(['appointment', 'client', 'employee']);
    }

    /**
     * Validate the QR payload structure.
     */
    private function isValidPayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $requiredKeys = [
            'appointment_id',
            'business_id',
            'employee_id',
            'client_id',
            'expires_at',
            'generated_at',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a QR code is valid without creating a visit.
     */
    public function isValidQR(string $qrCode): bool
    {
        try {
            $payload = Crypt::decrypt($qrCode);

            if (! $this->isValidPayload($payload)) {
                return false;
            }

            // Check expiration
            if (Carbon::now()->timestamp > $payload['expires_at']) {
                return false;
            }

            // Check appointment exists
            $appointment = Appointment::find($payload['appointment_id']);

            return $appointment !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get QR code expiration time.
     */
    public function getQRExpiration(string $qrCode): ?Carbon
    {
        try {
            $payload = Crypt::decrypt($qrCode);

            if (! $this->isValidPayload($payload)) {
                return null;
            }

            return Carbon::createFromTimestamp($payload['expires_at']);
        } catch (\Exception $e) {
            return null;
        }
    }
}
