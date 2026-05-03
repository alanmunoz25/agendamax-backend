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

class PayrollDashboardTest extends TestCase
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
        ]);
    }

    /** @test */
    public function test_it_shows_dashboard_with_kpis(): void
    {
        PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);

        $response = $this->actingAs($this->admin)->get('/payroll/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Dashboard')
            ->has('kpis')
            ->has('kpis.total_paid_this_year')
            ->has('kpis.current_period_total')
            ->has('kpis.active_employees_count')
            ->has('kpis.has_periods')
            ->where('kpis.has_periods', true)
        );
    }

    /** @test */
    public function test_it_shows_empty_state_when_no_periods(): void
    {
        $response = $this->actingAs($this->admin)->get('/payroll/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Dashboard')
            ->where('kpis.has_periods', false)
        );
    }

    /** @test */
    public function test_it_requires_authentication(): void
    {
        $this->get('/payroll/dashboard')->assertRedirect('/login');
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

        // Create data for our business
        $period = PayrollPeriod::factory()->forBusiness($this->business)->create(['status' => 'open']);
        PayrollRecord::factory()->create([
            'business_id' => $this->business->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'gross_total' => 500,
        ]);

        // Other admin sees their own empty data (not ours)
        $response = $this->actingAs($otherAdmin)->get('/payroll/dashboard');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.has_periods', false)
        );
    }

    /** @test */
    public function test_it_counts_active_employees_correctly(): void
    {
        $inactiveUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);
        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $inactiveUser->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/payroll/dashboard');

        $response->assertOk();
        // Only the 1 active employee from setUp counts
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.active_employees_count', 1)
        );
    }
}
