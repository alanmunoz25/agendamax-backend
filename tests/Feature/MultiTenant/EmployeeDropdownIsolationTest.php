<?php

declare(strict_types=1);

namespace Tests\Feature\MultiTenant;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Track A — Sprint 6: Issue #8.1 regression test.
 *
 * Verifies that the employee dropdown on the appointment edit form
 * only contains employees belonging to the acting admin's business.
 */
class EmployeeDropdownIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_edit_employees_dropdown_is_scoped_to_own_business(): void
    {
        $biz1 = Business::factory()->create(['name' => 'Business One']);
        $biz2 = Business::factory()->create(['name' => 'Business Two']);

        $admin1 = User::factory()->create([
            'business_id' => $biz1->id,
            'role' => 'business_admin',
        ]);

        // 5 active employees in biz1
        Employee::factory()->count(5)->create([
            'business_id' => $biz1->id,
            'is_active' => true,
        ]);

        // 5 active employees in biz2 — must NOT appear in biz1 admin's dropdown
        Employee::factory()->count(5)->create([
            'business_id' => $biz2->id,
            'is_active' => true,
        ]);

        // Create a service and client for biz1 to attach to the appointment
        $service = Service::factory()->create(['business_id' => $biz1->id]);
        $client = User::factory()->create([
            'business_id' => $biz1->id,
            'role' => 'client',
        ]);

        // Pick any biz1 employee for the appointment
        $biz1Employee = Employee::withoutGlobalScopes()
            ->where('business_id', $biz1->id)
            ->first();

        $appointment = Appointment::factory()->create([
            'business_id' => $biz1->id,
            'service_id' => $service->id,
            'employee_id' => $biz1Employee->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($admin1)
            ->get("/appointments/{$appointment->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Appointments/Edit')
            ->has('employees', 5)
        );

        // Additionally verify all returned employees belong to biz1
        $response->assertInertia(function ($page) use ($biz1): void {
            $employees = $page->toArray()['props']['employees'];

            foreach ($employees as $emp) {
                $dbEmployee = Employee::withoutGlobalScopes()->find($emp['id']);
                $this->assertSame($biz1->id, $dbEmployee->business_id,
                    "Employee {$emp['id']} belongs to business {$dbEmployee->business_id}, expected {$biz1->id}"
                );
            }
        });
    }
}
