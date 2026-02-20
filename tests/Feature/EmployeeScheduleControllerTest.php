<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Business $otherBusiness;

    private User $businessAdmin;

    private User $otherBusinessAdmin;

    private User $employeeUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'name' => 'Test Business',
        ]);

        $this->otherBusiness = Business::factory()->create([
            'name' => 'Other Business',
        ]);

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->otherBusinessAdmin = User::factory()->create([
            'business_id' => $this->otherBusiness->id,
            'role' => 'business_admin',
        ]);

        $this->employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
        ]);
    }

    public function test_business_admin_can_view_employee_schedule_index(): void
    {
        // Create a schedule for the employee
        $schedule = EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}/schedules");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Schedule/Index')
            ->has('employee')
            ->where('employee.id', $this->employee->id)
            ->has('schedules', 1)
            ->has('schedules.0', fn ($item) => $item
                ->where('id', $schedule->id)
                ->where('day_of_week', 1)
                ->where('start_time', '09:00')
                ->where('end_time', '17:00')
                ->etc()
            )
        );
    }

    public function test_business_admin_can_view_empty_schedule(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}/schedules");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Schedule/Index')
            ->has('schedules', 0)
        );
    }

    public function test_business_admin_can_view_employee_schedule_edit_page(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/employees/{$this->employee->id}/schedules/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Employees/Schedule/Edit')
            ->has('employee')
            ->has('schedules')
        );
    }

    public function test_business_admin_can_update_employee_schedule(): void
    {
        $scheduleData = [
            'schedules' => [
                [
                    'day_of_week' => 1, // Monday
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'is_available' => true,
                ],
                [
                    'day_of_week' => 2, // Tuesday
                    'start_time' => '10:00',
                    'end_time' => '18:00',
                    'is_available' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", $scheduleData);

        $response->assertRedirect("/employees/{$this->employee->id}/schedules");
        $response->assertSessionHas('success', 'Employee schedule updated successfully.');

        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_available' => true,
        ]);

        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $this->employee->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '18:00',
            'is_available' => true,
        ]);
    }

    public function test_updating_schedule_replaces_existing_schedules(): void
    {
        // Create existing schedules
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 2,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $newScheduleData = [
            'schedules' => [
                [
                    'day_of_week' => 3, // Wednesday
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'is_available' => true,
                ],
            ],
        ];

        $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", $newScheduleData);

        // Old schedules should be deleted
        $this->assertDatabaseMissing('employee_schedules', [
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
        ]);

        $this->assertDatabaseMissing('employee_schedules', [
            'employee_id' => $this->employee->id,
            'day_of_week' => 2,
        ]);

        // New schedule should exist
        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $this->employee->id,
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    public function test_schedule_validation_requires_schedules_array(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", []);

        $response->assertSessionHasErrors('schedules');
    }

    public function test_schedule_validation_rejects_invalid_day_of_week(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", [
                'schedules' => [
                    [
                        'day_of_week' => 7, // Invalid: should be 0-6
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('schedules.0.day_of_week');
    }

    public function test_schedule_validation_rejects_invalid_time_format(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '25:00', // Invalid: hour > 23
                        'end_time' => '17:00',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('schedules.0.start_time');
    }

    public function test_schedule_validation_rejects_duplicate_days(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '09:00',
                        'end_time' => '12:00',
                    ],
                    [
                        'day_of_week' => 1, // Duplicate day
                        'start_time' => '13:00',
                        'end_time' => '17:00',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('schedules');
    }

    public function test_schedule_validation_rejects_end_time_before_start_time(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '17:00',
                        'end_time' => '09:00', // Before start time
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('schedules.0.end_time');
    }

    public function test_business_admin_can_delete_all_schedules(): void
    {
        // Create schedules
        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
        ]);

        EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 2,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->delete("/employees/{$this->employee->id}/schedules");

        $response->assertRedirect("/employees/{$this->employee->id}/schedules");
        $response->assertSessionHas('success', 'Employee schedule cleared successfully.');

        $this->assertDatabaseMissing('employee_schedules', [
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_other_business_admin_cannot_view_employee_schedule(): void
    {
        $response = $this->actingAs($this->otherBusinessAdmin)
            ->get("/employees/{$this->employee->id}/schedules");

        // Returns 404 due to global scope - employee not found in other business
        $response->assertNotFound();
    }

    public function test_other_business_admin_cannot_update_employee_schedule(): void
    {
        $response = $this->actingAs($this->otherBusinessAdmin)
            ->put("/employees/{$this->employee->id}/schedules", [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                    ],
                ],
            ]);

        // Returns 404 due to global scope - employee not found in other business
        $response->assertNotFound();
    }

    public function test_other_business_admin_cannot_delete_employee_schedule(): void
    {
        $response = $this->actingAs($this->otherBusinessAdmin)
            ->delete("/employees/{$this->employee->id}/schedules");

        // Returns 404 due to global scope - employee not found in other business
        $response->assertNotFound();
    }

    public function test_guest_cannot_access_employee_schedules(): void
    {
        $response = $this->get("/employees/{$this->employee->id}/schedules");
        $response->assertRedirect('/login');

        $response = $this->get("/employees/{$this->employee->id}/schedules/edit");
        $response->assertRedirect('/login');

        $response = $this->put("/employees/{$this->employee->id}/schedules", []);
        $response->assertRedirect('/login');

        $response = $this->delete("/employees/{$this->employee->id}/schedules");
        $response->assertRedirect('/login');
    }

    public function test_schedules_are_deleted_when_employee_is_deleted(): void
    {
        $schedule = EmployeeSchedule::factory()->create([
            'employee_id' => $this->employee->id,
            'day_of_week' => 1,
        ]);

        $this->employee->delete();

        $this->assertDatabaseMissing('employee_schedules', [
            'id' => $schedule->id,
        ]);
    }
}
