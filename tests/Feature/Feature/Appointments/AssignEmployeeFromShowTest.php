<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Pivots\AppointmentService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the Assign/Change Employee flow from /appointments/{id} (Show page).
 *
 * Issue UX #1 — Sprint 6 QA Round 2.
 * Verifies:
 *   - GET /appointments/{id} returns the service lines prop (used by the modal).
 *   - PATCH changes the employee_id in the DB.
 *   - A service line without an employee shows the unassigned state.
 */
class AssignEmployeeFromShowTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Employee $employeeA;

    private Employee $employeeB;

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
            'name' => 'Pedicure',
            'price' => 1200,
            'duration' => 60,
            'is_active' => true,
        ]);

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
    }

    /**
     * GET /appointments/{id} includes appointment_service_lines prop
     * so the frontend modal can render the assign/change UI.
     */
    public function test_show_page_includes_service_lines_prop(): void
    {
        $line = $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Appointments/Show')
                ->has('appointment_service_lines', 1)
                ->where('appointment_service_lines.0.id', $line->id)
                ->where('appointment_service_lines.0.employee.id', $this->employeeA->id),
        );
    }

    /**
     * GET /appointments/{id} — service line without employee shows null employee.
     */
    public function test_show_page_service_line_with_no_employee_has_null_employee(): void
    {
        $line = $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => null,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('appointment_service_lines', 1)
                ->where('appointment_service_lines.0.id', $line->id)
                ->where('appointment_service_lines.0.employee', null),
        );
    }

    /**
     * PATCH endpoint assigns employee to a previously unassigned service line.
     */
    public function test_patch_assigns_employee_to_unassigned_service_line(): void
    {
        /** @var AppointmentService $line */
        $line = $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => null,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$line->id}", [
                'employee_id' => $this->employeeA->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'id' => $line->id,
            'employee_id' => $this->employeeA->id,
        ]);
    }

    /**
     * PATCH endpoint changes employee on an already-assigned service line.
     */
    public function test_patch_changes_employee_on_assigned_service_line(): void
    {
        /** @var AppointmentService $line */
        $line = $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->patch("/appointments/{$this->appointment->id}/services/{$line->id}", [
                'employee_id' => $this->employeeB->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointment_services', [
            'id' => $line->id,
            'employee_id' => $this->employeeB->id,
        ]);
    }
}
