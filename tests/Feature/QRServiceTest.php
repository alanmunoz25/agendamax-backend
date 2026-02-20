<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\QRService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class QRServiceTest extends TestCase
{
    use RefreshDatabase;

    private QRService $service;

    private Business $business;

    private User $businessAdmin;

    private User $employee;

    private User $client;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new QRService;

        // Create business
        $this->business = Business::factory()->create([
            'loyalty_stamps_required' => 10,
        ]);

        // Create business admin
        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Create employee user
        $this->employee = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Create employee record
        $employeeRecord = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employee->id,
            'is_active' => true,
        ]);

        // Create client
        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Create service
        $service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
            'duration' => 60,
            'price' => 50.00,
        ]);

        // Create confirmed appointment
        $this->appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $service->id,
            'employee_id' => $employeeRecord->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(10, 0),
            'scheduled_until' => Carbon::tomorrow()->setTime(11, 0),
            'status' => 'confirmed',
        ]);

        // Authenticate as business admin
        $this->actingAs($this->businessAdmin);
    }

    /** @test */
    public function it_generates_qr_code_successfully(): void
    {
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        $this->assertNotEmpty($qrCode);
        $this->assertIsString($qrCode);

        // Decrypt and verify payload
        $payload = Crypt::decrypt($qrCode);
        $this->assertEquals($this->appointment->id, $payload['appointment_id']);
        $this->assertEquals($this->business->id, $payload['business_id']);
        $this->assertEquals($this->appointment->employee_id, $payload['employee_id']);
        $this->assertEquals($this->client->id, $payload['client_id']);
        $this->assertArrayHasKey('expires_at', $payload);
        $this->assertArrayHasKey('generated_at', $payload);
    }

    /** @test */
    public function it_throws_exception_when_generating_qr_for_non_confirmed_appointment(): void
    {
        $this->appointment->update(['status' => 'pending']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only generate QR for confirmed appointments');

        $this->service->generateVisitQR($this->appointment->id);
    }

    /** @test */
    public function it_throws_exception_when_generating_qr_for_different_business(): void
    {
        // Create another business and user
        $otherBusiness = Business::factory()->create();
        $otherUser = User::factory()->create(['business_id' => $otherBusiness->id]);

        // Authenticate as other user
        $this->actingAs($otherUser);

        // The global scope will prevent finding the appointment from a different business
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->generateVisitQR($this->appointment->id);
    }

    /** @test */
    public function it_verifies_qr_code_successfully(): void
    {
        // Generate QR code
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        // Authenticate as client
        $this->actingAs($this->client);

        // Verify QR code
        $visit = $this->service->verifyQR($qrCode);

        $this->assertInstanceOf(Visit::class, $visit);
        $this->assertEquals($this->appointment->id, $visit->appointment_id);
        $this->assertEquals($this->client->id, $visit->client_id);
        $this->assertEquals($this->business->id, $visit->business_id);
        $this->assertNotNull($visit->verified_at);
        $this->assertFalse($visit->stamp_awarded);
    }

    /** @test */
    public function it_throws_exception_for_tampered_qr_code(): void
    {
        $this->actingAs($this->client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or tampered QR code');

        $this->service->verifyQR('invalid-qr-code-string');
    }

    /** @test */
    public function it_throws_exception_for_expired_qr_code(): void
    {
        // Create expired QR code payload
        $payload = [
            'appointment_id' => $this->appointment->id,
            'business_id' => $this->business->id,
            'employee_id' => $this->appointment->employee_id,
            'client_id' => $this->client->id,
            'expires_at' => Carbon::now()->subHours(1)->timestamp, // Expired 1 hour ago
            'generated_at' => Carbon::now()->subHours(25)->timestamp,
        ];

        $expiredQrCode = Crypt::encrypt($payload);

        $this->actingAs($this->client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('QR code has expired');

        $this->service->verifyQR($expiredQrCode);
    }

    /** @test */
    public function it_throws_exception_for_already_used_qr_code(): void
    {
        // Generate and verify QR code first time
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        $this->actingAs($this->client);
        $this->service->verifyQR($qrCode);

        // Try to use same QR code again
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('QR code has already been used');

        $this->service->verifyQR($qrCode);
    }

    /** @test */
    public function it_throws_exception_for_wrong_client(): void
    {
        // Generate QR code
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        // Create another client
        $otherClient = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Authenticate as different client
        $this->actingAs($otherClient);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('QR code is not for this client');

        $this->service->verifyQR($qrCode);
    }

    /** @test */
    public function it_throws_exception_for_cancelled_appointment(): void
    {
        // Generate QR code for confirmed appointment
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        // Cancel the appointment
        $this->appointment->update(['status' => 'cancelled']);

        $this->actingAs($this->client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Appointment is not in valid state for check-in');

        $this->service->verifyQR($qrCode);
    }

    /** @test */
    public function it_updates_pending_appointment_to_confirmed_on_verification(): void
    {
        // Create pending appointment
        $pendingAppointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->appointment->service_id,
            'employee_id' => $this->appointment->employee_id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(14, 0),
            'scheduled_until' => Carbon::tomorrow()->setTime(15, 0),
            'status' => 'pending',
        ]);

        // Update to confirmed to generate QR, then set back to pending
        $pendingAppointment->update(['status' => 'confirmed']);
        $qrCode = $this->service->generateVisitQR($pendingAppointment->id);
        $pendingAppointment->update(['status' => 'pending']);

        $this->actingAs($this->client);

        // Verify QR code
        $this->service->verifyQR($qrCode);

        // Check appointment status was updated
        $this->assertEquals('confirmed', $pendingAppointment->fresh()->status);
    }

    /** @test */
    public function it_validates_qr_code_without_creating_visit(): void
    {
        // Generate valid QR code
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        // Check validity without verification
        $isValid = $this->service->isValidQR($qrCode);

        $this->assertTrue($isValid);

        // Ensure no visit was created
        $this->assertDatabaseMissing('visits', [
            'appointment_id' => $this->appointment->id,
        ]);
    }

    /** @test */
    public function it_returns_false_for_invalid_qr_code(): void
    {
        $isValid = $this->service->isValidQR('invalid-qr-code');

        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_returns_qr_expiration_time(): void
    {
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        $expirationTime = $this->service->getQRExpiration($qrCode);

        $this->assertInstanceOf(Carbon::class, $expirationTime);
        $this->assertTrue($expirationTime->isFuture());
        $this->assertTrue($expirationTime->isAfter(Carbon::now()->addHours(23)));
    }

    /** @test */
    public function it_returns_null_for_invalid_qr_expiration(): void
    {
        $expirationTime = $this->service->getQRExpiration('invalid-qr-code');

        $this->assertNull($expirationTime);
    }

    /** @test */
    public function it_enforces_multi_tenant_isolation(): void
    {
        // Generate QR code
        $qrCode = $this->service->generateVisitQR($this->appointment->id);

        // Decrypt and modify business_id
        $payload = Crypt::decrypt($qrCode);
        $payload['business_id'] = 99999; // Different business
        $tamperedQrCode = Crypt::encrypt($payload);

        $this->actingAs($this->client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid business association');

        $this->service->verifyQR($tamperedQrCode);
    }
}
