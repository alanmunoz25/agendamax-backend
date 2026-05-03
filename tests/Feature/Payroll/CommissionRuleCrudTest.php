<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRuleCrudTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private Employee $employee;

    private Service $service;

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
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_it_returns_rules_with_scope_type(): void
    {
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10,
            'priority' => 1,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get('/payroll/commission-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/CommissionRules/Index')
            ->has('rules')
            ->has('employees')
            ->has('services')
            ->has('rules.data.0.scope_type')
            ->where('rules.data.0.scope_type', 'global')
        );
    }

    /** @test */
    public function test_it_creates_global_rule(): void
    {
        $response = $this->actingAs($this->admin)->post('/payroll/commission-rules', [
            'scope_type' => 'global',
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10,
            'effective_from' => now()->toDateString(),
            'effective_until' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('commission_rules', [
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10,
            'priority' => 1,
        ]);
    }

    /** @test */
    public function test_it_creates_specific_rule_with_priority_4(): void
    {
        $response = $this->actingAs($this->admin)->post('/payroll/commission-rules', [
            'scope_type' => 'specific',
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'type' => 'fixed',
            'value' => 25.50,
            'effective_from' => now()->toDateString(),
            'effective_until' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('commission_rules', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'type' => 'fixed',
            'priority' => 4,
        ]);
    }

    /** @test */
    public function test_it_updates_rule(): void
    {
        $rule = CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'percentage',
            'value' => 10,
            'priority' => 1,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->put("/payroll/commission-rules/{$rule->id}", [
            'scope_type' => 'global',
            'employee_id' => null,
            'service_id' => null,
            'type' => 'fixed',
            'value' => 50,
            'effective_from' => now()->toDateString(),
            'effective_until' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('commission_rules', [
            'id' => $rule->id,
            'type' => 'fixed',
            'value' => 50,
        ]);
    }

    /** @test */
    public function test_it_toggles_is_active_on_destroy(): void
    {
        $rule = CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->delete("/payroll/commission-rules/{$rule->id}");

        // Should toggle to inactive, not delete
        $this->assertDatabaseHas('commission_rules', [
            'id' => $rule->id,
            'is_active' => false,
        ]);

        // Toggle back
        $this->actingAs($this->admin)->delete("/payroll/commission-rules/{$rule->id}");
        $this->assertDatabaseHas('commission_rules', [
            'id' => $rule->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_it_validates_required_employee_for_per_employee_scope(): void
    {
        $response = $this->actingAs($this->admin)->post('/payroll/commission-rules', [
            'scope_type' => 'per_employee',
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => 10,
            'effective_from' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('employee_id');
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

        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($otherAdmin)->get('/payroll/commission-rules');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rules.meta.total', 0)
        );
    }

    /** @test */
    public function test_it_requires_authentication(): void
    {
        $this->get('/payroll/commission-rules')->assertRedirect('/login');
    }
}
