<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollEmployeeHistoricalTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'base_salary' => 1500.00,
        ]);
    }

    /** @test */
    public function test_it_shows_employee_history(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => 2000,
            'commissions_total' => 500,
            'tips_total' => 100,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/payroll/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Employees/Show')
            ->has('employee')
            ->has('totals')
            ->has('records')
            ->where('totals.records_count', 1)
        );
    }

    /** @test */
    public function test_it_updates_base_salary(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch("/payroll/employees/{$this->employee->id}/base-salary", [
                'base_salary' => 2500.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee->id,
            'base_salary' => 2500.00,
        ]);
    }

    /** @test */
    public function test_it_validates_base_salary(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch("/payroll/employees/{$this->employee->id}/base-salary", [
                'base_salary' => -100,
            ]);

        $response->assertSessionHasErrors('base_salary');
    }

    /** @test */
    public function test_it_exports_csv_with_bom(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => 2000,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/payroll/employees/{$this->employee->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        // Check BOM is present
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    /** @test */
    public function test_it_cannot_see_other_business_employee(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherEmployeeUser = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'employee',
        ]);
        $otherEmployee = Employee::factory()->create([
            'business_id' => $otherBusiness->id,
            'user_id' => $otherEmployeeUser->id,
        ]);

        // Our admin should not be able to access other business's employee
        $response = $this->actingAs($this->admin)
            ->get("/payroll/employees/{$otherEmployee->id}");

        // BelongsToBusiness scope causes 404
        $response->assertNotFound();
    }

    /** @test */
    public function test_it_requires_authentication_for_employee_show(): void
    {
        $this->get("/payroll/employees/{$this->employee->id}")
            ->assertRedirect('/login');
    }
}
