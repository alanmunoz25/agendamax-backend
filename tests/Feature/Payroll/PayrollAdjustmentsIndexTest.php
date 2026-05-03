<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAdjustmentsIndexTest extends TestCase
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
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
        ]);
    }

    /** @test */
    public function test_it_renders_adjustments_index(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollAdjustment::factory()->forPeriod($period)->credit(100)->create([
            'employee_id' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/payroll/adjustments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Adjustments/Index')
            ->has('adjustments')
            ->has('totals')
            ->has('filters')
            ->has('employees_for_filter')
            ->has('periods_for_filter')
        );
    }

    /** @test */
    public function test_it_returns_correct_totals(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollAdjustment::factory()->forPeriod($period)->credit(200)->create([
            'employee_id' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);
        PayrollAdjustment::factory()->forPeriod($period)->debit(50)->create([
            'employee_id' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/payroll/adjustments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('totals.credits', '200.00')
            ->where('totals.debits', '50.00')
            ->where('totals.net', '150.00')
        );
    }

    /** @test */
    public function test_it_filters_by_type(): void
    {
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollAdjustment::factory()->forPeriod($period)->credit(100)->create([
            'employee_id' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);
        PayrollAdjustment::factory()->forPeriod($period)->debit(50)->create([
            'employee_id' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/payroll/adjustments?type=credit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adjustments.meta.total', 1)
        );
    }

    /** @test */
    public function test_it_enforces_business_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $period = PayrollPeriod::factory()->forBusiness($this->business)->create();
        PayrollAdjustment::factory()->forPeriod($period)->credit(999)->create([
            'employee_id' => $this->employee->id,
            'created_by' => $this->admin->id,
        ]);

        // Other admin should see 0 adjustments (different business)
        $response = $this->actingAs($otherAdmin)->get('/payroll/adjustments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adjustments.meta.total', 0)
        );
    }

    /** @test */
    public function test_it_requires_authentication(): void
    {
        $this->get('/payroll/adjustments')->assertRedirect('/login');
    }
}
