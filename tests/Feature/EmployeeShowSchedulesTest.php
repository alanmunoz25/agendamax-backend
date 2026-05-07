<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Mejora #1 — schedules prop on EmployeeController::show().
 */
class EmployeeShowSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Employee $employee;

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
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);
    }

    public function test_employee_show_includes_schedules_prop(): void
    {
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Show')
            ->has('schedules', 1)
            ->has('schedules.0', fn ($schedule) => $schedule
                ->where('day_of_week', 1)
                ->where('start_time', '09:00') // EmployeeSchedule accessor returns HH:MM (no seconds)
                ->where('end_time', '18:00')
                ->where('is_available', true)
                ->etc()
            )
        );
    }

    public function test_employee_show_schedules_prop_is_empty_array_when_no_schedules(): void
    {
        // Employee has no schedules
        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Show')
            ->has('schedules', 0)
        );
    }

    public function test_employee_show_schedules_contains_all_7_days_when_configured(): void
    {
        for ($day = 0; $day <= 6; $day++) {
            EmployeeSchedule::factory()->create([
                'employee_id' => $this->employee->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_available' => $day >= 1 && $day <= 5, // Mon-Fri available, Sat-Sun not
            ]);
        }

        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Show')
            ->has('schedules', 7)
        );
    }

    public function test_employee_show_schedule_with_is_available_false_is_included(): void
    {
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 0, // Sunday
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'is_available' => false,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('schedules', 1)
            ->has('schedules.0', fn ($schedule) => $schedule
                ->where('is_available', false)
                ->etc()
            )
        );
    }

    public function test_employee_show_schedules_only_belong_to_this_employee(): void
    {
        // Create schedule for another employee
        $otherEmployeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $otherEmployeeUser->id,
        ]);

        EmployeeSchedule::factory()->create([
            'employee_id' => $otherEmployee->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_available' => true,
        ]);

        // Our employee has 2 schedules
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
        ]);

        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 3,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('schedules', 2) // Only this employee's schedules
        );
    }
}
