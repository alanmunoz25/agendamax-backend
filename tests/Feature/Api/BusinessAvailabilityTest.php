<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAvailabilityTest extends TestCase
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
    }

    public function test_can_get_availability_with_employee_id(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        $response = $this->getJson(
            "/api/v1/businesses/{$this->business->id}/availability?service_id={$this->service->id}&employee_id={$this->employee->id}&date={$date}"
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'slots' => [
                    '*' => ['start', 'end'],
                ],
            ])
            ->assertJsonPath('date', $date);
    }

    public function test_can_get_availability_without_employee_id(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        $response = $this->getJson(
            "/api/v1/businesses/{$this->business->id}/availability?service_id={$this->service->id}&date={$date}"
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'slots' => [
                    '*' => ['start', 'end', 'employee_id', 'employee_name'],
                ],
            ])
            ->assertJsonPath('date', $date);
    }

    public function test_availability_requires_service_id_and_date(): void
    {
        $response = $this->getJson(
            "/api/v1/businesses/{$this->business->id}/availability"
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_id', 'date']);
    }

    public function test_availability_does_not_require_authentication(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        // No Sanctum::actingAs — this is a public endpoint
        $response = $this->getJson(
            "/api/v1/businesses/{$this->business->id}/availability?service_id={$this->service->id}&employee_id={$this->employee->id}&date={$date}"
        );

        $response->assertStatus(200);
    }

    public function test_availability_returns_404_for_invalid_business(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        $response = $this->getJson(
            "/api/v1/businesses/99999/availability?service_id={$this->service->id}&date={$date}"
        );

        $response->assertStatus(404);
    }
}
