<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Pivots\AppointmentService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Issue #8.2 — AppointmentServiceController::update().
 * PATCH /appointments/{appointment}/services/{appointmentService}
 * Allows reassigning the employee on an existing appointment service line.
 */
class AppointmentServiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Employee $employeeA;

    private Employee $employeeB;

    private Service $service;

    private Appointment $appointment;

    private AppointmentService $serviceLine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $userA = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'name' => 'Ana López',
        ]);

        $this->employeeA = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $userA->id,
            'is_active' => true,
        ]);

        $userB = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'name' => 'Beatriz Soto',
        ]);

        $this->employeeB = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $userB->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'price' => 800,
            'duration' => 45,
            'is_active' => true,
        ]);

        // Both employees provide the service
        $this->employeeA->services()->attach($this->service->id);
        $this->employeeB->services()->attach($this->service->id);

        $clientUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $this->appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employeeA->id,
            'client_id' => $clientUser->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        // Create a service line assigned to employeeA
        $this->serviceLine = $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employeeA->id,
        ]);
    }

    /**
     * Happy path: business admin reassigns a service line from employeeA to employeeB.
     */
    public function test_business_admin_can_change_employee_on_service_line(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $this->employeeB->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'id' => $this->serviceLine->id,
            'employee_id' => $this->employeeB->id,
        ]);
    }

    /**
     * Cross-business reject: admin of biz2 tries to edit a service line on biz1 appointment.
     */
    public function test_admin_from_different_business_cannot_update_service_line(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        $response = $this->actingAs($otherAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $this->employeeB->id,
            ]);

        // BelongsToBusinessScope returns 404 for cross-business appointments
        $response->assertNotFound();

        $this->assertDatabaseHas('appointment_services', [
            'id' => $this->serviceLine->id,
            'employee_id' => $this->employeeA->id,
        ]);
    }

    /**
     * Cross-business employee: admin of biz1 tries to assign an employee from biz2.
     */
    public function test_cannot_assign_employee_from_different_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherUserEmployee = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'employee',
        ]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $otherBusiness->id,
            'user_id' => $otherUserEmployee->id,
            'is_active' => true,
        ]);
        $otherEmployee->services()->attach($this->service->id);

        $response = $this->actingAs($this->businessAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $otherEmployee->id,
            ]);

        // BelongsToBusinessScope on Employee causes findOrFail to throw 404
        $response->assertNotFound();

        $this->assertDatabaseHas('appointment_services', [
            'id' => $this->serviceLine->id,
            'employee_id' => $this->employeeA->id,
        ]);
    }

    /**
     * Employee who does not offer the service cannot be assigned to that service line.
     */
    public function test_cannot_assign_employee_who_does_not_provide_the_service(): void
    {
        $userC = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $employeeC = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $userC->id,
            'is_active' => true,
        ]);
        // employeeC is NOT attached to $this->service

        $response = $this->actingAs($this->businessAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $employeeC->id,
            ]);

        $response->assertSessionHasErrors(['employee_id']);

        $this->assertDatabaseHas('appointment_services', [
            'id' => $this->serviceLine->id,
            'employee_id' => $this->employeeA->id,
        ]);
    }

    /**
     * Client role is forbidden from updating service lines.
     */
    public function test_client_cannot_update_service_line(): void
    {
        $clientUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($clientUser)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $this->employeeB->id,
            ]);

        $response->assertForbidden();
    }

    /**
     * Employee role is forbidden from updating service lines.
     */
    public function test_employee_user_cannot_update_service_line(): void
    {
        $response = $this->actingAs($this->employeeA->user)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $this->employeeB->id,
            ]);

        $response->assertForbidden();
    }

    /**
     * Guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
            'employee_id' => $this->employeeB->id,
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Validation: employee_id is required.
     */
    public function test_validation_requires_employee_id(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", []);

        $response->assertSessionHasErrors(['employee_id']);
    }

    /**
     * Super admin can reassign any service line.
     */
    public function test_super_admin_can_update_service_line(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'business_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$this->serviceLine->id}", [
                'employee_id' => $this->employeeB->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'id' => $this->serviceLine->id,
            'employee_id' => $this->employeeB->id,
        ]);
    }
}
