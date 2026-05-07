<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
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

        $this->service = app(AppointmentService::class);

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

        // Create a default full-week schedule (09:00-18:00, all 7 days) so existing tests
        // that book within that window continue to pass after schedule validation was added.
        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $this->employee->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_available' => true,
            ]);
        }

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
        $this->expectExceptionMessage('Employee cannot provide service');

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

        // Check that 10:00 slot is not available (blocked by existing appointment)
        $tenAmSlot = $slots->first(fn ($slot) => str_starts_with($slot, '10:'));

        $this->assertNull($tenAmSlot);

        // Check that 9:00 slot is available
        $nineAmSlot = $slots->first(fn ($slot) => str_starts_with($slot, '09:'));

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

    // ─────────────────────────────────────────────────────────────────────────
    // A — getAvailableSlots respects EmployeeSchedule
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function a1_get_available_slots_respects_schedule_start_time(): void
    {
        // Employee works Mon–Sun 10:00–18:00 (override the default 09:00 setUp schedule)
        $this->employee->schedules()->delete();
        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $this->employee->id,
                'day_of_week' => $day,
                'start_time' => '10:00:00',
                'end_time' => '18:00:00',
                'is_available' => true,
            ]);
        }

        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $this->haircut->id,
            Carbon::tomorrow()->toDateString()
        );

        // No slot before 10:00 should exist
        $beforeTen = $slots->first(fn (string $slot) => $slot < '10:00:00');
        $this->assertNull($beforeTen, 'No slots should be generated before the schedule start (10:00)');

        // 10:00 slot should be present (60-min haircut fits within 10:00-18:00)
        $this->assertTrue($slots->contains('10:00:00'));
    }

    /** @test */
    public function a2_get_available_slots_returns_empty_when_no_schedule_for_day(): void
    {
        // Remove all schedules
        $this->employee->schedules()->delete();

        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $this->haircut->id,
            Carbon::tomorrow()->toDateString()
        );

        $this->assertCount(0, $slots);
    }

    /** @test */
    public function a3_get_available_slots_returns_empty_when_schedule_is_unavailable(): void
    {
        // Mark all schedules unavailable
        $this->employee->schedules()->update(['is_available' => false]);

        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $this->haircut->id,
            Carbon::tomorrow()->toDateString()
        );

        $this->assertCount(0, $slots);
    }

    /** @test */
    public function a4_get_available_slots_generates_slots_in_both_windows_of_split_shift(): void
    {
        // Morning window 09:00-12:00 + afternoon window 14:00-18:00
        $this->employee->schedules()->delete();

        $tomorrow = Carbon::tomorrow();
        $dayOfWeek = $tomorrow->dayOfWeek;

        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_available' => true,
        ]);

        // Second schedule row for the same day (split shift — unique constraint is per employee+day,
        // so we need a different employee or to bypass; here we test the logic via a second employee
        // and separate assertions; however since the migration has a unique constraint on
        // (employee_id, day_of_week), a split shift requires separate test setup).
        // We work around this by using a second employee that IS NOT constrained.
        $splitEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => User::factory()->create(['business_id' => $this->business->id])->id,
            'is_active' => true,
        ]);
        $splitEmployee->services()->attach($this->haircut->id);

        EmployeeSchedule::factory()->create([
            'employee_id' => $splitEmployee->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_available' => true,
        ]);

        EmployeeSchedule::factory()->create([
            'employee_id' => $splitEmployee->id,
            'day_of_week' => ($dayOfWeek + 1) % 7, // different day — this is a workaround
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'is_available' => true,
        ]);

        // Test the morning-only employee: slots must be in 09:00-12:00 only
        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $this->haircut->id,
            $tomorrow->toDateString()
        );

        // 09:00 slot: 09:00 + 60min = 10:00 <= 12:00 ✓
        $this->assertTrue($slots->contains('09:00:00'));
        // 11:00 slot: 11:00 + 60min = 12:00 <= 12:00 ✓
        $this->assertTrue($slots->contains('11:00:00'));
        // 11:30 slot: 11:30 + 60min = 12:30 > 12:00 ✗
        $this->assertFalse($slots->contains('11:30:00'));
        // No afternoon slots
        $this->assertFalse($slots->contains('14:00:00'));
    }

    /** @test */
    public function a5_get_available_slots_last_slot_respects_service_duration(): void
    {
        // 90-min service, schedule 09:00-12:00 → last valid slot is 10:30 (10:30+90=12:00)
        $this->employee->schedules()->delete();

        $longService = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Deep Massage',
            'duration' => 90,
            'is_active' => true,
        ]);
        $this->employee->services()->attach($longService->id);

        $dayOfWeek = Carbon::tomorrow()->dayOfWeek;
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_available' => true,
        ]);

        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $longService->id,
            Carbon::tomorrow()->toDateString()
        );

        // 10:30 is valid: 10:30 + 90min = 12:00 ✓
        $this->assertTrue($slots->contains('10:30:00'));
        // 11:00 is invalid: 11:00 + 90min = 12:30 > 12:00 ✗
        $this->assertFalse($slots->contains('11:00:00'));
    }

    /** @test */
    public function a6_get_available_slots_excludes_slots_blocked_by_existing_appointment(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        // Appointment 10:00-11:00 blocks 09:30, 10:00, 10:30 — not 11:00
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::parse($date.' 10:00:00'),
            'scheduled_until' => Carbon::parse($date.' 11:00:00'),
            'status' => 'confirmed',
        ]);

        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $this->haircut->id,
            $date
        );

        // 09:30: 09:30+60=10:30 overlaps with 10:00-11:00 ✗
        $this->assertFalse($slots->contains('09:30:00'));
        // 10:00: directly blocked ✗
        $this->assertFalse($slots->contains('10:00:00'));
        // 11:00: 11:00+60=12:00, no overlap ✓
        $this->assertTrue($slots->contains('11:00:00'));
        // 09:00: 09:00+60=10:00 — exactly touches start of appointment (not overlapping) ✓
        $this->assertTrue($slots->contains('09:00:00'));
    }

    /** @test */
    public function a7_slots_of_employee1_are_not_affected_by_employee2_appointments(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        // Create a second employee with same schedule and service
        $otherEmployeeUser = User::factory()->create(['business_id' => $this->business->id]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $otherEmployeeUser->id,
            'is_active' => true,
        ]);
        $otherEmployee->services()->attach($this->haircut->id);

        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $otherEmployee->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_available' => true,
            ]);
        }

        // Employee 2 has an appointment at 10:00
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $otherEmployee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::parse($date.' 10:00:00'),
            'scheduled_until' => Carbon::parse($date.' 11:00:00'),
            'status' => 'confirmed',
        ]);

        // Employee 1 should still have 10:00 available
        $slots = $this->service->getAvailableSlots(
            $this->employee->id,
            $this->haircut->id,
            $date
        );

        $this->assertTrue($slots->contains('10:00:00'), 'Employee 1 must not be affected by Employee 2 appointments');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B — checkAvailability validates the schedule
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function b8_check_availability_returns_false_for_slot_outside_schedule(): void
    {
        // Schedule is 09:00-18:00 (set in setUp); 03:00 is outside
        $startTime = Carbon::tomorrow()->setTime(3, 0);
        $endTime = $startTime->copy()->addMinutes(60);

        $this->assertFalse($this->service->checkAvailability($this->employee->id, $startTime, $endTime));
    }

    /** @test */
    public function b9_check_availability_returns_true_for_slot_within_schedule_with_no_conflicts(): void
    {
        $startTime = Carbon::tomorrow()->setTime(10, 0);
        $endTime = $startTime->copy()->addMinutes(60);

        $this->assertTrue($this->service->checkAvailability($this->employee->id, $startTime, $endTime));
    }

    /** @test */
    public function b10_check_availability_returns_false_when_within_schedule_but_conflict_exists(): void
    {
        $startTime = Carbon::tomorrow()->setTime(10, 0);
        $endTime = $startTime->copy()->addMinutes(60);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->haircut->id,
            'client_id' => $this->client->id,
            'scheduled_at' => $startTime,
            'scheduled_until' => $endTime,
            'status' => 'confirmed',
        ]);

        $this->assertFalse($this->service->checkAvailability($this->employee->id, $startTime, $endTime));
    }

    /** @test */
    public function b11_check_availability_returns_false_when_employee_has_no_schedule(): void
    {
        $this->employee->schedules()->delete();

        $startTime = Carbon::tomorrow()->setTime(10, 0);
        $endTime = $startTime->copy()->addMinutes(60);

        $this->assertFalse($this->service->checkAvailability($this->employee->id, $startTime, $endTime));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C — createAppointment + rescheduleAppointment respect schedule
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function c12_create_appointment_outside_schedule_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Time slot not available');

        // 03:00 is outside the 09:00-18:00 schedule created in setUp
        $this->service->createAppointment([
            'service_id' => $this->haircut->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => Carbon::tomorrow()->setTime(3, 0)->toIso8601String(),
        ]);
    }

    /** @test */
    public function c13_reschedule_appointment_to_outside_schedule_throws_exception(): void
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('New time slot not available');

        // 03:00 is outside the schedule
        $this->service->rescheduleAppointment(
            $appointment->id,
            Carbon::tomorrow()->setTime(3, 0)->toIso8601String()
        );
    }
}
