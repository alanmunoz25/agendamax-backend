<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Mejora #4 — AppointmentServiceController::store().
 * POST /appointments/{appointment}/services
 */
class AppointmentServiceStoreTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Employee $employee;

    private Service $service;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'name' => 'María García',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'price' => 800,
            'duration' => 45,
            'is_active' => true,
        ]);

        // Attach service to employee
        $this->employee->services()->attach($this->service->id);

        $clientUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $this->appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $clientUser->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);
    }

    /**
     * Happy path: business admin adds a service line to an appointment.
     */
    public function test_business_admin_can_add_service_to_appointment(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $this->appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    /**
     * Cross-business reject: appointment belongs to another business.
     */
    public function test_cross_business_appointment_is_rejected(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        // The appointment belongs to $this->business, not $otherBusiness.
        // BelongsToBusinessScope will return 404 for otherAdmin.
        $response = $this->actingAs($otherAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('appointment_services', [
            'appointment_id' => $this->appointment->id,
        ]);
    }

    /**
     * Employee does not provide the requested service → redirect back with validation error.
     */
    public function test_rejects_when_employee_does_not_provide_service(): void
    {
        $otherService = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Keratina',
            'is_active' => true,
        ]);

        // employee is NOT attached to otherService

        $response = $this->actingAs($this->businessAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $otherService->id,
            ]);

        // Inertia web requests redirect back with session errors (not JSON 422)
        $response->assertSessionHasErrors(['service_id']);

        $this->assertDatabaseMissing('appointment_services', [
            'appointment_id' => $this->appointment->id,
            'service_id' => $otherService->id,
        ]);
    }

    /**
     * Employee from another business → 404 (BelongsToBusinessScope filters the employee out).
     * The multi-tenant global scope prevents cross-business employee lookups.
     */
    public function test_rejects_employee_from_different_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherEmployeeUser = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'employee',
        ]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $otherBusiness->id,
            'user_id' => $otherEmployeeUser->id,
            'is_active' => true,
        ]);
        $otherEmployee->services()->attach($this->service->id);

        $response = $this->actingAs($this->businessAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $otherEmployee->id,
                'service_id' => $this->service->id,
            ]);

        // BelongsToBusinessScope on Employee model causes findOrFail to throw ModelNotFoundException → 404
        $response->assertNotFound();

        $this->assertDatabaseMissing('appointment_services', [
            'appointment_id' => $this->appointment->id,
        ]);
    }

    /**
     * Duplicate service in same appointment → redirect back with validation error.
     */
    public function test_rejects_duplicate_service_in_same_appointment(): void
    {
        // Add the service line first
        $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
        ]);

        // Try to add the same service again
        $response = $this->actingAs($this->businessAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        // Inertia web requests redirect back with session errors (not JSON 422)
        $response->assertSessionHasErrors(['service_id']);

        $this->assertDatabaseCount('appointment_services', 1); // Only the original line exists
    }

    /**
     * Authorization: client role is rejected (403).
     */
    public function test_client_cannot_add_service_to_appointment(): void
    {
        $clientUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($clientUser)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        $response->assertForbidden();
    }

    /**
     * Authorization: lead role is rejected (403).
     */
    public function test_lead_cannot_add_service_to_appointment(): void
    {
        $leadUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'lead',
        ]);

        $response = $this->actingAs($leadUser)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        $response->assertForbidden();
    }

    /**
     * Authorization: employee role is rejected (403).
     */
    public function test_employee_user_cannot_add_service_to_appointment(): void
    {
        $response = $this->actingAs($this->employee->user)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        $response->assertForbidden();
    }

    /**
     * Guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post("/appointments/{$this->appointment->id}/services", [
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Validation: employee_id is required.
     */
    public function test_validation_requires_employee_id(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'service_id' => $this->service->id,
            ]);

        $response->assertSessionHasErrors(['employee_id']);
    }

    /**
     * Validation: service_id is required.
     */
    public function test_validation_requires_service_id(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
            ]);

        $response->assertSessionHasErrors(['service_id']);
    }

    /**
     * Super admin can add service to any appointment.
     */
    public function test_super_admin_can_add_service_to_any_appointment(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'business_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)
            ->post("/appointments/{$this->appointment->id}/services", [
                'employee_id' => $this->employee->id,
                'service_id' => $this->service->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $this->appointment->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
        ]);
    }
}
