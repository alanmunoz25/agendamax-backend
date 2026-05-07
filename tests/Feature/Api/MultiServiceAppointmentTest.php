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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiServiceAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $client;

    private Employee $employee1;

    private Employee $employee2;

    private Service $service1;

    private Service $service2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $employeeUser1 = User::factory()->create([
            'business_id' => $this->business->id,
        ]);
        $employeeUser2 = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $this->employee1 = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser1->id,
            'is_active' => true,
        ]);
        $this->employee2 = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser2->id,
            'is_active' => true,
        ]);

        $this->service1 = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Haircut',
            'duration' => 60,
            'price' => 50.00,
        ]);
        $this->service2 = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Coloring',
            'duration' => 90,
            'price' => 80.00,
        ]);

        $this->employee1->services()->attach($this->service1->id);
        $this->employee2->services()->attach($this->service2->id);

        // Provide a full-week schedule for both employees so booking tests pass schedule validation
        foreach ([$this->employee1, $this->employee2] as $emp) {
            for ($day = 0; $day <= 6; $day++) {
                EmployeeSchedule::factory()->create([
                    'employee_id' => $emp->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'is_available' => true,
                ]);
            }
        }
    }

    public function test_can_create_appointment_with_services_array(): void
    {
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'services' => [
                ['service_id' => $this->service1->id, 'employee_id' => $this->employee1->id],
                ['service_id' => $this->service2->id, 'employee_id' => $this->employee2->id],
            ],
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'notes' => 'Multi-service appointment',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'appointment' => [
                    'id',
                    'status',
                    'scheduled_at',
                    'scheduled_until',
                    'service',
                    'employee',
                    'services',
                ],
            ]);

        // Verify the services array in response
        $services = $response->json('appointment.services');
        $this->assertCount(2, $services);

        // Verify pivot records exist
        $appointmentId = $response->json('appointment.id');
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointmentId,
            'service_id' => $this->service1->id,
            'employee_id' => $this->employee1->id,
        ]);
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointmentId,
            'service_id' => $this->service2->id,
            'employee_id' => $this->employee2->id,
        ]);

        // Verify scheduled_until uses the longest service duration (90 min)
        $expectedUntil = $scheduledAt->copy()->addMinutes(90);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'scheduled_until' => $expectedUntil->toDateTimeString(),
        ]);
    }

    public function test_backward_compat_with_single_service_id(): void
    {
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'service_id' => $this->service1->id,
            'employee_id' => $this->employee1->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201);

        $appointmentId = $response->json('appointment.id');

        // Verify backward-compat columns are set
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'service_id' => $this->service1->id,
            'employee_id' => $this->employee1->id,
        ]);

        // Verify pivot record also exists
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointmentId,
            'service_id' => $this->service1->id,
            'employee_id' => $this->employee1->id,
        ]);
    }

    public function test_validation_fails_without_service_id_or_services(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/appointments', [
            'scheduled_at' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id', 'services']);
    }

    public function test_validation_fails_with_empty_services_array(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/appointments', [
            'services' => [],
            'scheduled_at' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['services']);
    }

    public function test_validation_fails_with_invalid_service_id_in_services_array(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/appointments', [
            'services' => [
                ['service_id' => 99999, 'employee_id' => $this->employee1->id],
            ],
            'scheduled_at' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['services.0.service_id']);
    }

    public function test_employee_who_cannot_provide_service_is_rejected(): void
    {
        Sanctum::actingAs($this->client);

        // employee1 can only do service1, not service2
        $response = $this->postJson('/api/v1/appointments', [
            'services' => [
                ['service_id' => $this->service2->id, 'employee_id' => $this->employee1->id],
            ],
            'scheduled_at' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Employee cannot provide service: Coloring');
    }

    public function test_services_array_without_employee(): void
    {
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'services' => [
                ['service_id' => $this->service1->id],
            ],
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201);

        $appointmentId = $response->json('appointment.id');
        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointmentId,
            'service_id' => $this->service1->id,
            'employee_id' => null,
        ]);
    }

    public function test_response_includes_services_array_with_details(): void
    {
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'services' => [
                ['service_id' => $this->service1->id, 'employee_id' => $this->employee1->id],
                ['service_id' => $this->service2->id, 'employee_id' => $this->employee2->id],
            ],
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertStatus(201);

        $services = $response->json('appointment.services');
        $this->assertCount(2, $services);

        // Verify each service entry has expected fields
        $serviceIds = array_column($services, 'service_id');
        $this->assertContains($this->service1->id, $serviceIds);
        $this->assertContains($this->service2->id, $serviceIds);

        foreach ($services as $service) {
            $this->assertArrayHasKey('service_id', $service);
            $this->assertArrayHasKey('name', $service);
            $this->assertArrayHasKey('duration', $service);
            $this->assertArrayHasKey('price', $service);
            $this->assertArrayHasKey('employee_id', $service);
        }
    }

    public function test_list_appointments_includes_services(): void
    {
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        // Create a multi-service appointment
        $this->postJson('/api/v1/appointments', [
            'services' => [
                ['service_id' => $this->service1->id, 'employee_id' => $this->employee1->id],
            ],
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response = $this->getJson('/api/v1/appointments');

        $response->assertStatus(200);

        $appointments = $response->json('data');
        $this->assertNotEmpty($appointments);
        $this->assertArrayHasKey('services', $appointments[0]);
    }
}
