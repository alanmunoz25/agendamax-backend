<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $client;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->client = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

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
    }

    public function test_client_can_list_their_appointments(): void
    {
        Sanctum::actingAs($this->client);

        Appointment::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
        ]);

        $response = $this->getJson('/api/v1/appointments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'business_id',
                        'client_id',
                        'scheduled_at',
                        'scheduled_until',
                        'status',
                        'notes',
                        'service',
                        'employee',
                        'business',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_client_can_create_appointment(): void
    {
        Sanctum::actingAs($this->client);

        $scheduledAt = Carbon::tomorrow()->setTime(14, 0);

        $response = $this->postJson('/api/v1/appointments', [
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'notes' => 'Please bring portfolio',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'appointment' => [
                    'id',
                    'status',
                    'scheduled_at',
                    'service',
                    'employee',
                    'business',
                ],
            ]);

        $this->assertDatabaseHas('appointments', [
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'status' => 'pending',
        ]);
    }

    public function test_appointment_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/appointments', [
            'service_id' => $this->service->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => Carbon::tomorrow()->toIso8601String(),
        ]);

        $response->assertStatus(401);
    }

    public function test_appointment_creation_validates_input(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->postJson('/api/v1/appointments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id', 'scheduled_at']);
    }

    public function test_client_can_view_their_appointment(): void
    {
        Sanctum::actingAs($this->client);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
        ]);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'business_id',
                    'client_id',
                    'scheduled_at',
                    'scheduled_until',
                    'status',
                    'service',
                    'employee',
                    'business',
                ],
            ]);
    }

    public function test_client_cannot_view_others_appointments(): void
    {
        Sanctum::actingAs($this->client);

        $otherClient = User::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $otherClient->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
        ]);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(403);
    }

    public function test_client_can_cancel_their_appointment(): void
    {
        Sanctum::actingAs($this->client);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $this->client->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'status' => 'confirmed',
        ]);

        $response = $this->deleteJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Appointment cancelled successfully',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_client_can_get_available_slots(): void
    {
        Sanctum::actingAs($this->client);

        $date = Carbon::tomorrow()->toDateString();

        $response = $this->getJson("/api/v1/appointments/availability?employee_id={$this->employee->id}&service_id={$this->service->id}&date={$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'slots' => [
                    '*' => ['start', 'end'],
                ],
            ])
            ->assertJsonPath('date', $date);
    }

    public function test_availability_requires_valid_parameters(): void
    {
        Sanctum::actingAs($this->client);

        $response = $this->getJson('/api/v1/appointments/availability');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'service_id', 'date']);
    }
}
