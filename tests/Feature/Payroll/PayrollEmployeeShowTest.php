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

/**
 * Track D — PayrollEmployeeController::show() tests.
 * Verifies that commission records show up in /payroll/employees/{id}
 * once a PayrollRecord has been generated for the period.
 */
class PayrollEmployeeShowTest extends TestCase
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
    public function test_employee_show_renders_correct_inertia_component(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get("/payroll/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Employees/Show')
            ->has('employee')
            ->has('totals')
            ->has('records')
            ->has('chart_series')
        );
    }

    /** @test */
    public function test_employee_show_displays_commission_from_active_period(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'status' => 'open',
        ]);

        // Create a PayrollRecord with commissions (represents a completed payroll cycle)
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'base_salary_snapshot' => '1500.00',
            'commissions_total' => '250.00',
            'tips_total' => '50.00',
            'adjustments_total' => '0.00',
            'gross_total' => '1800.00',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get("/payroll/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Employees/Show')
            ->where('totals.records_count', 1)
            ->where('totals.commissions_total', '250.00')
        );
    }

    /** @test */
    public function test_employee_show_totals_aggregate_multiple_periods(): void
    {
        $period1 = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
            'status' => 'closed',
        ]);
        $period2 = PayrollPeriod::factory()->forBusiness($this->business)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);

        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period1->id,
            'employee_id' => $this->employee->id,
            'commissions_total' => '100.00',
            'gross_total' => '1600.00',
        ]);

        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period2->id,
            'employee_id' => $this->employee->id,
            'commissions_total' => '200.00',
            'gross_total' => '1700.00',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get("/payroll/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('totals.records_count', 2)
        );

        // Verify totals values numerically (SQLite may return integers without decimals)
        $responseData = $response->original->getData();
        $totals = $responseData['page']['props']['totals'];
        $this->assertEquals(300.00, (float) $totals['commissions_total']);
        $this->assertEquals(3300.00, (float) $totals['gross_total_all_time']);
    }

    /** @test */
    public function test_employee_show_returns_empty_when_no_payroll_records(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get("/payroll/employees/{$this->employee->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('totals.records_count', 0)
            ->has('records.data', 0)
        );
    }

    /** @test */
    public function test_employee_show_is_multi_tenant_isolated(): void
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

        $this->actingAs($this->admin);

        // BelongsToBusiness scope returns 404 for other-business employee
        $this->get("/payroll/employees/{$otherEmployee->id}")
            ->assertNotFound();
    }

    /** @test */
    public function test_employee_show_requires_authentication(): void
    {
        $this->get("/payroll/employees/{$this->employee->id}")
            ->assertRedirect('/login');
    }
}
