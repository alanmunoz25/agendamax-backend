<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private User $client;

    private Employee $employee;

    private Service $service;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
            'price' => 50,
            'duration' => 30,
            'is_active' => true,
        ]);

        // Attach service to employee so they can provide it
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

        $this->appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'scheduled_until' => now()->addDay()->setTime(10, 30),
            'status' => 'pending',
        ]);
    }

    public function test_business_admin_can_view_appointments_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Index')
            ->has('appointments.data', 1)
            ->has('appointments.data.0', fn ($appointment) => $appointment
                ->where('id', $this->appointment->id)
                ->where('client_id', $this->client->id)
                ->etc()
            )
        );
    }

    public function test_appointments_index_can_be_searched_by_client(): void
    {
        $anotherClient = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
            'name' => 'Jane Doe',
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $anotherClient->id,
            'scheduled_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/appointments?search=Jane');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.client.name', 'Jane Doe')
        );
    }

    public function test_appointments_index_can_be_filtered_by_status(): void
    {
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => now()->addDays(3),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/appointments?status=confirmed');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.status', 'confirmed')
        );
    }

    public function test_appointments_index_can_be_filtered_by_employee(): void
    {
        $anotherEmployeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $anotherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $anotherEmployeeUser->id,
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $anotherEmployee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/appointments?employee_id='.$anotherEmployee->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.employee_id', $anotherEmployee->id)
        );
    }

    public function test_business_admin_can_view_create_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/appointments/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Create')
            ->has('employees')
            ->has('services')
            ->has('clients')
        );
    }

    public function test_business_admin_can_create_appointment(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/appointments', [
                'client_id' => $this->client->id,
                'service_id' => $this->service->id,
                'employee_id' => $this->employee->id,
                'scheduled_at' => now()->addDays(5)->setTime(10, 0)->toIso8601String(),
                'notes' => 'Test appointment notes',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'notes' => 'Test appointment notes',
        ]);
    }

    public function test_business_admin_cannot_create_appointment_with_invalid_data(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/appointments', [
                'service_id' => 999999,  // Invalid: non-existent service
                'employee_id' => 999999,  // Invalid: non-existent employee
                'scheduled_at' => now()->subDay()->toIso8601String(),  // Invalid: past date
            ]);

        $response->assertSessionHasErrors(['service_id', 'employee_id', 'scheduled_at']);
    }

    public function test_business_admin_can_view_appointment(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Show')
            ->has('appointment', fn ($appointment) => $appointment
                ->where('id', $this->appointment->id)
                ->where('client_id', $this->client->id)
                ->etc()
            )
        );
    }

    public function test_business_admin_can_view_edit_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/appointments/{$this->appointment->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Edit')
            ->has('appointment')
            ->has('employees')
            ->has('services')
            ->has('statuses')
        );
    }

    public function test_business_admin_can_update_appointment(): void
    {
        $newService = Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        // Attach new service to employee
        $this->employee->services()->attach($newService->id);

        $response = $this->actingAs($this->businessAdmin)
            ->put("/appointments/{$this->appointment->id}", [
                'service_id' => $newService->id,
                'employee_id' => $this->employee->id,
                'scheduled_at' => now()->addDays(10)->toIso8601String(),
                'status' => 'confirmed',
                'notes' => 'Updated notes',
            ]);

        $response->assertRedirect("/appointments/{$this->appointment->id}");
        $response->assertSessionHas('success');

        $this->appointment->refresh();
        $this->assertEquals($newService->id, $this->appointment->service_id);
        $this->assertEquals('confirmed', $this->appointment->status);
        $this->assertEquals('Updated notes', $this->appointment->notes);
    }

    public function test_business_admin_can_cancel_appointment(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->delete("/appointments/{$this->appointment->id}");

        $response->assertRedirect('/appointments');
        $response->assertSessionHas('success');

        $this->appointment->refresh();
        $this->assertEquals('cancelled', $this->appointment->status);
    }

    public function test_employee_can_view_but_not_create_appointments(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        // Can view
        $response = $this->actingAs($employeeUser)
            ->get('/appointments');

        $response->assertOk();

        // Cannot create
        $response = $this->actingAs($employeeUser)
            ->get('/appointments/create');

        $response->assertForbidden();
    }

    public function test_employee_cannot_update_appointment(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employeeUser)
            ->put("/appointments/{$this->appointment->id}", [
                'status' => 'confirmed',
            ]);

        $response->assertForbidden();

        $this->appointment->refresh();
        $this->assertEquals('pending', $this->appointment->status);
    }

    public function test_employee_cannot_cancel_appointment(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employeeUser)
            ->delete("/appointments/{$this->appointment->id}");

        $response->assertForbidden();

        $this->appointment->refresh();
        $this->assertEquals('pending', $this->appointment->status);
    }

    public function test_user_from_different_business_cannot_access_appointment(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        // Should not see the appointment
        $response = $this->actingAs($otherAdmin)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointments.data', 0)
        );

        // Cannot view (404 because global scope filters it out)
        $response = $this->actingAs($otherAdmin)
            ->get("/appointments/{$this->appointment->id}");

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_appointment_routes(): void
    {
        $this->get('/appointments')->assertRedirect('/login');
        $this->get('/appointments/create')->assertRedirect('/login');
        $this->post('/appointments', [])->assertRedirect('/login');
        $this->get("/appointments/{$this->appointment->id}")->assertRedirect('/login');
        $this->get("/appointments/{$this->appointment->id}/edit")->assertRedirect('/login');
        $this->put("/appointments/{$this->appointment->id}", [])->assertRedirect('/login');
        $this->delete("/appointments/{$this->appointment->id}")->assertRedirect('/login');
    }

    public function test_user_without_business_cannot_access_appointment_routes(): void
    {
        $userWithoutBusiness = User::factory()->create([
            'business_id' => null,
            'role' => 'client',
        ]);

        $response = $this->actingAs($userWithoutBusiness)
            ->get('/appointments');

        $response->assertForbidden();
    }

    public function test_appointment_requires_service_id(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/appointments', [
                'employee_id' => $this->employee->id,
                'scheduled_at' => now()->addDay()->toIso8601String(),
            ]);

        $response->assertSessionHasErrors(['service_id']);
    }

    public function test_can_create_appointment_without_employee(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/appointments', [
                'client_id' => $this->client->id,
                'service_id' => $this->service->id,
                'scheduled_at' => now()->addDays(5)->toIso8601String(),
                'notes' => 'No employee preference',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'employee_id' => null,
            'notes' => 'No employee preference',
        ]);
    }

    public function test_appointment_requires_future_scheduled_at(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/appointments', [
                'service_id' => $this->service->id,
                'employee_id' => $this->employee->id,
                'scheduled_at' => now()->subDay()->toIso8601String(),
            ]);

        $response->assertSessionHasErrors(['scheduled_at']);
    }

    public function test_appointment_notes_are_optional(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/appointments', [
                'client_id' => $this->client->id,
                'service_id' => $this->service->id,
                'employee_id' => $this->employee->id,
                'scheduled_at' => now()->addDays(2)->setTime(11, 0)->toIso8601String(),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'notes' => null,
        ]);
    }

    public function test_employee_sees_only_their_assigned_appointments(): void
    {
        $employeeUser = $this->employee->user;

        // Create another employee with their own appointment
        $otherEmployeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $otherEmployeeUser->id,
            'is_active' => true,
        ]);
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $otherEmployee->id,
            'client_id' => $this->client->id,
            'scheduled_at' => now()->addDays(2),
        ]);

        // The employee should only see the appointment assigned to them
        $response = $this->actingAs($employeeUser)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.employee_id', $this->employee->id)
        );
    }

    public function test_client_sees_only_their_own_appointments(): void
    {
        $otherClient = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'client_id' => $otherClient->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        // The client should only see their own appointment
        $response = $this->actingAs($this->client)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.client_id', $this->client->id)
        );
    }

    public function test_business_admin_sees_all_appointments_with_full_permissions(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.create', true)
            ->where('can.manage', true)
            ->where('can.cancel', true)
            ->where('can.filter_employees', true)
            ->where('can.filter_services', true)
        );
    }

    public function test_employee_receives_restricted_permissions(): void
    {
        $employeeUser = $this->employee->user;

        $response = $this->actingAs($employeeUser)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.create', false)
            ->where('can.manage', false)
            ->where('can.cancel', false)
            ->where('can.filter_employees', false)
            ->where('can.filter_services', true)
        );
    }

    public function test_client_receives_appropriate_permissions(): void
    {
        $response = $this->actingAs($this->client)
            ->get('/appointments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.create', true)
            ->where('can.manage', false)
            ->where('can.cancel', true)
            ->where('can.filter_employees', false)
            ->where('can.filter_services', false)
        );
    }
}
