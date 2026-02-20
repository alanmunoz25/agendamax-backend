<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentService $service;

    private Business $business;

    private User $businessAdmin;

    private User $client;

    private Employee $employee;

    private Service $haircut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AppointmentService;

        // Create a business with admin
        $this->business = Business::factory()->create([
            'loyalty_stamps_required' => 10,
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        // Create employee
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        // Create service
        $this->haircut = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
            'duration' => 60, // 60 minutes
            'price' => 50.00,
            'is_active' => true,
        ]);

        // Attach service to employee
        $this->employee->services()->attach($this->haircut->id);

        // Authenticate as business admin
        $this->actingAs($this->businessAdmin);
    }

    /** @test */
    public function it_creates_appointment_successfully(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);

        $appointment = $this->service->createAppointment([
            'service_id' => $this->haircut->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'notes' => 'First haircut',
        ]);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($this->business->id, $appointment->business_id);
        $this->assertEquals($this->haircut->id, $appointment->service_id);
        $this->assertEquals($this->employee->id, $appointment->employee_id);
        $this->assertEquals($this->client->id, $appointment->client_id);
        $this->assertEquals('pending', $appointment->status);
        $this->assertEquals('First haircut', $appointment->notes);
        $this->assertEquals($scheduledAt, $appointment->scheduled_at);
        $this->assertEquals($scheduledAt->copy()->addMinutes(60), $appointment->scheduled_until);
    }

    /** @test */
    public function it_throws_exception_when_employee_cannot_provide_service(): void
    {
        // Create another service not attached to employee
        $massage = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Massage',
            'duration' => 90,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Employee cannot provide this service');

        $this->service->createAppointment([
            'service_id' => $massage->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(10, 0)->toIso8601String(),
        ]);
    }

    /** @test */
    public function it_throws_exception_when_time_slot_not_available(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        // Create existing appointment
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_until' => $scheduledAt->copy()->addMinutes(60),
            'status' => 'confirmed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Time slot not available');

        // Try to book overlapping appointment
        $this->service->createAppointment([
            'service_id' => $this->haircut->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(30)->toIso8601String(),
        ]);
    }

    /** @test */
    public function it_checks_availability_correctly(): void
    {
        $startTime = Carbon::tomorrow()->setTime(10, 0);
        $endTime = $startTime->copy()->addMinutes(60);

        // Initially available
        $this->assertTrue($this->service->checkAvailability($this->employee->id, $startTime, $endTime));

        // Create appointment
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $startTime,
            'scheduled_until' => $endTime,
            'status' => 'confirmed',
        ]);

        // No longer available
        $this->assertFalse($this->service->checkAvailability($this->employee->id, $startTime, $endTime));
    }

    /** @test */
    public function it_ignores_cancelled_appointments_when_checking_availability(): void
    {
        $startTime = Carbon::tomorrow()->setTime(10, 0);
        $endTime = $startTime->copy()->addMinutes(60);

        // Create cancelled appointment
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $startTime,
            'scheduled_until' => $endTime,
            'status' => 'cancelled',
        ]);

        // Should be available because cancelled appointments don't count
        $this->assertTrue($this->service->checkAvailability($this->employee->id, $startTime, $endTime));
    }

    /** @test */
    public function it_returns_available_slots(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        // Create appointment at 10:00-11:00
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::parse($date.' 10:00:00'),
            'scheduled_until' => Carbon::parse($date.' 11:00:00'),
            'status' => 'confirmed',
        ]);

        $slots = $this->service->getAvailableSlots($this->employee->id, $this->haircut->id, $date);

        $this->assertGreaterThan(0, $slots->count());

        // Check that 10:00 slot is not available
        $tenAmSlot = $slots->first(function ($slot) {
            return Carbon::parse($slot['start'])->hour === 10;
        });

        $this->assertNull($tenAmSlot);

        // Check that 9:00 slot is available
        $nineAmSlot = $slots->first(function ($slot) {
            return Carbon::parse($slot['start'])->hour === 9;
        });

        $this->assertNotNull($nineAmSlot);
    }

    /** @test */
    public function it_returns_empty_slots_when_employee_cannot_provide_service(): void
    {
        $massage = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Massage',
        ]);

        $slots = $this->service->getAvailableSlots($this->employee->id, $massage->id, Carbon::tomorrow()->toDateString());

        $this->assertCount(0, $slots);
    }

    /** @test */
    public function it_cancels_appointment_successfully(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(10, 0),
            'scheduled_until' => Carbon::tomorrow()->setTime(11, 0),
            'status' => 'confirmed',
        ]);

        $cancelled = $this->service->cancelAppointment($appointment->id, 'Client requested cancellation');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('Client requested cancellation', $cancelled->cancellation_reason);
    }

    /** @test */
    public function it_throws_exception_when_cancelling_completed_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::yesterday(),
            'scheduled_until' => Carbon::yesterday()->addHour(),
            'status' => 'completed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot cancel appointment with status: completed');

        $this->service->cancelAppointment($appointment->id);
    }

    /** @test */
    public function it_reschedules_appointment_successfully(): void
    {
        $originalTime = Carbon::tomorrow()->setTime(10, 0);
        $newTime = Carbon::tomorrow()->setTime(14, 0);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $originalTime,
            'scheduled_until' => $originalTime->copy()->addMinutes(60),
            'status' => 'confirmed',
        ]);

        $rescheduled = $this->service->rescheduleAppointment($appointment->id, $newTime->toIso8601String());

        $this->assertEquals($newTime, $rescheduled->scheduled_at);
        $this->assertEquals($newTime->copy()->addMinutes(60), $rescheduled->scheduled_until);
    }

    /** @test */
    public function it_throws_exception_when_rescheduling_to_unavailable_slot(): void
    {
        $originalTime = Carbon::tomorrow()->setTime(10, 0);
        $conflictTime = Carbon::tomorrow()->setTime(14, 0);

        // Create existing appointment at 14:00
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $conflictTime,
            'scheduled_until' => $conflictTime->copy()->addMinutes(60),
            'status' => 'confirmed',
        ]);

        // Create appointment to reschedule
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $originalTime,
            'scheduled_until' => $originalTime->copy()->addMinutes(60),
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('New time slot not available');

        $this->service->rescheduleAppointment($appointment->id, $conflictTime->toIso8601String());
    }

    /** @test */
    public function it_enforces_multi_tenant_isolation(): void
    {
        // Create another business
        $otherBusiness = Business::factory()->create();
        $otherUser = User::factory()->create(['business_id' => $otherBusiness->id]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $otherBusiness->id,
            'user_id' => $otherUser->id,
        ]);

        // Try to access employee from different business
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->createAppointment([
            'service_id' => $this->haircut->id,
            'employee_id' => $otherEmployee->id, // Different business
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(10, 0)->toIso8601String(),
        ]);
    }
}
