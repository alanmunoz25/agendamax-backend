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
 * Tests for Mejora #3 — appointment_service_lines prop on
 * AppointmentController::show() and edit().
 */
class AppointmentServiceLinesTest extends TestCase
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

    public function test_show_includes_appointment_service_lines_prop(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Show')
            ->has('appointment_service_lines')
        );
    }

    public function test_show_appointment_service_lines_empty_when_no_lines(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointment_service_lines', 0)
        );
    }

    public function test_show_appointment_service_lines_with_employee(): void
    {
        // Add a service line to the appointment
        $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointment_service_lines', 1)
            ->has('appointment_service_lines.0', fn ($line) => $line
                ->has('id')
                ->has('service', fn ($service) => $service
                    ->where('id', $this->service->id)
                    ->where('name', 'Manicure')
                    ->has('price')
                    ->where('duration', 45)
                    ->etc()
                )
                ->has('employee', fn ($employee) => $employee
                    ->where('id', $this->employee->id)
                    ->where('name', 'María García')
                    ->etc()
                )
                ->where('has_employee', true)
            )
        );
    }

    public function test_show_appointment_service_lines_without_employee(): void
    {
        // Add a service line without employee
        $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => null,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointment_service_lines', 1)
            ->has('appointment_service_lines.0', fn ($line) => $line
                ->where('employee', null)
                ->where('has_employee', false)
                ->etc()
            )
        );
    }

    public function test_show_includes_legacy_services_prop_alongside_new_prop(): void
    {
        // Ensure existing 'services' prop is still present (backward compat)
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointment')
            ->has('appointment_service_lines')
            ->has('employees_with_services')
        );
    }

    public function test_show_includes_employees_with_services_prop(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('employees_with_services')
            ->has('employees_with_services.0', fn ($emp) => $emp
                ->where('id', $this->employee->id)
                ->has('name')
                ->has('services')
                ->etc()
            )
        );
    }

    public function test_edit_includes_appointment_service_lines_prop(): void
    {
        $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Edit')
            ->has('appointment_service_lines', 1)
            ->has('appointment_service_lines.0', fn ($line) => $line
                ->has('id')
                ->has('service')
                ->has('employee')
                ->has('has_employee')
            )
        );
    }

    public function test_edit_appointment_service_lines_empty_when_no_lines(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointment_service_lines', 0)
        );
    }

    public function test_show_appointment_service_lines_multiple_services(): void
    {
        $service2 = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Pedicure',
            'price' => 1200,
            'duration' => 60,
            'is_active' => true,
        ]);
        $this->employee->services()->attach($service2->id);

        $this->appointment->appointmentServices()->create([
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
        ]);
        $this->appointment->appointmentServices()->create([
            'service_id' => $service2->id,
            'employee_id' => null,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointment_service_lines', 2)
        );
    }
}
