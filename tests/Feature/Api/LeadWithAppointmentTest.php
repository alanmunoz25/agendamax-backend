<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadWithAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
            'duration' => 60,
            'price' => 50.00,
        ]);

        $this->employee->services()->attach($this->service->id);

        // Provide a full-week schedule so appointment booking tests pass schedule validation
        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $this->employee->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_available' => true,
            ]);
        }
    }

    public function test_creates_lead_and_appointment(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1234567890',
            'business_id' => $this->business->id,
            'source' => 'appointment_form',
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'appointment_notes' => 'First visit',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'lead' => ['id', 'name', 'email', 'phone', 'role', 'business_id'],
                'appointment' => ['id', 'business_id', 'client_id', 'scheduled_at', 'status', 'service', 'employee'],
            ])
            ->assertJsonPath('lead.role', 'lead')
            ->assertJsonPath('lead.email', 'jane@example.com')
            ->assertJsonPath('appointment.status', 'pending');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'primary_business_id' => $this->business->id,
            'role' => 'lead',
        ]);

        $this->assertDatabaseHas('appointments', [
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'status' => 'pending',
            'notes' => 'First visit',
        ]);
    }

    public function test_reuses_existing_lead_for_same_email_and_business(): void
    {
        $existingLead = User::factory()->create([
            'email' => 'existing@example.com',
            'business_id' => $this->business->id,
            'role' => 'lead',
            'name' => 'Original Name',
        ]);

        $scheduledAt = Carbon::tomorrow()->setTime(10, 0);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Updated Name',
            'email' => 'existing@example.com',
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('lead.id', $existingLead->id);

        // No duplicate user created
        $this->assertDatabaseCount('users', 2); // employee user + existing lead

        // Appointment linked to existing lead
        $this->assertDatabaseHas('appointments', [
            'client_id' => $existingLead->id,
        ]);
    }

    public function test_updates_existing_lead_info_on_reuse(): void
    {
        User::factory()->create([
            'email' => 'lead@example.com',
            'business_id' => $this->business->id,
            'role' => 'lead',
            'name' => 'Old Name',
            'phone' => null,
        ]);

        $scheduledAt = Carbon::tomorrow()->setTime(11, 0);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'New Name',
            'email' => 'lead@example.com',
            'phone' => '+9876543210',
            'business_id' => $this->business->id,
            'source' => 'appointment_form',
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('lead.name', 'New Name')
            ->assertJsonPath('lead.phone', '+9876543210');

        $this->assertDatabaseHas('users', [
            'email' => 'lead@example.com',
            'name' => 'New Name',
            'phone' => '+9876543210',
            'source' => 'appointment_form',
        ]);
    }

    public function test_validation_errors_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/leads/with-appointment', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'business_id',
                'service_id',
                'scheduled_at',
            ]);
    }

    public function test_creates_lead_and_appointment_without_employee(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'No Pref User',
            'email' => 'nopref@example.com',
            'business_id' => $this->business->id,
            'source' => 'appointment_form',
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('lead.email', 'nopref@example.com')
            ->assertJsonPath('appointment.status', 'pending');

        $this->assertDatabaseHas('appointments', [
            'service_id' => $this->service->id,
            'employee_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_fails_when_employee_cannot_provide_service(): void
    {
        $otherService = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);
        // Employee is NOT attached to $otherService

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'business_id' => $this->business->id,
            'service_id' => $otherService->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Employee cannot provide service', $response->json('error'));
    }

    public function test_fails_when_time_slot_unavailable(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        // Create a conflicting appointment
        $client = User::factory()->create(['business_id' => $this->business->id]);
        \App\Models\Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'client_id' => $client->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_until' => $scheduledAt->copy()->addMinutes(60),
            'status' => 'confirmed',
        ]);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Jane Doe',
            'email' => 'jane-conflict@example.com',
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Time slot not available', $response->json('error'));
    }

    public function test_transaction_rollback_lead_not_created_if_appointment_fails(): void
    {
        $otherService = Service::factory()->create([
            'business_id' => $this->business->id,
        ]);
        // Employee cannot provide $otherService → appointment will fail

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Rollback Test',
            'email' => 'rollback@example.com',
            'business_id' => $this->business->id,
            'service_id' => $otherService->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        // Lead should NOT exist because the transaction rolled back
        $this->assertDatabaseMissing('users', [
            'email' => 'rollback@example.com',
        ]);
    }

    public function test_no_auth_required(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        // No Sanctum::actingAs — this is a public endpoint
        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201);
    }

    public function test_appointment_notes_separate_from_lead_notes(): void
    {
        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/leads/with-appointment', [
            'name' => 'Notes Test',
            'email' => 'notes@example.com',
            'business_id' => $this->business->id,
            'notes' => 'Lead note about the customer',
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'appointment_notes' => 'Appointment-specific note',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'notes@example.com',
            'notes' => 'Lead note about the customer',
        ]);

        $this->assertDatabaseHas('appointments', [
            'notes' => 'Appointment-specific note',
        ]);
    }
}
