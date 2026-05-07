<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Mejora #8 — commission_rules prop on ServiceController::show().
 */
class ServiceCommissionRulesTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Employee $employee;

    private Service $service;

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
            'name' => 'María García',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'price' => 800,
            'duration' => 45,
            'is_active' => true,
        ]);
    }

    public function test_service_show_includes_commission_rules_prop(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Services/Show')
            ->has('commission_rules')
            ->has('global_rule_count')
            ->has('all_employees')
        );
    }

    public function test_service_show_commission_rules_empty_when_none_configured(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('commission_rules', 0)
            ->where('global_rule_count', 0)
        );
    }

    public function test_service_show_commission_rules_includes_per_service_rule(): void
    {
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => $this->service->id,
            'type' => 'percentage',
            'value' => 15.00,
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('commission_rules', 1)
            ->has('commission_rules.0', fn ($rule) => $rule
                ->where('scope_type', 'per_service')
                ->where('type', 'percentage')
                ->where('is_active', true)
                ->where('employee', null)
                ->etc()
            )
        );
    }

    public function test_service_show_commission_rules_includes_specific_rule_with_employee(): void
    {
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'service_id' => $this->service->id,
            'type' => 'fixed',
            'value' => 200.00,
            'is_active' => true,
            'effective_from' => '2026-03-01',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('commission_rules', 1)
            ->has('commission_rules.0', fn ($rule) => $rule
                ->where('scope_type', 'specific')
                ->where('type', 'fixed')
                ->has('employee', fn ($employee) => $employee
                    ->where('id', $this->employee->id)
                    ->where('name', 'María García')
                )
                ->etc()
            )
        );
    }

    public function test_service_show_does_not_include_rules_for_other_services(): void
    {
        $otherService = Service::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Pedicure',
            'is_active' => true,
        ]);

        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => $otherService->id,
            'type' => 'percentage',
            'value' => 10.00,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('commission_rules', 0) // No rules for this service
        );
    }

    public function test_service_show_global_rule_count_counts_active_global_rules(): void
    {
        // Create 2 active global rules for this business
        CommissionRule::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ]);

        // Create 1 inactive global rule (should not count)
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'is_active' => false,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('global_rule_count', 2)
        );
    }

    public function test_service_show_all_employees_includes_active_employees(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('all_employees', 1)
            ->has('all_employees.0', fn ($emp) => $emp
                ->where('id', $this->employee->id)
                ->where('name', 'María García')
            )
        );
    }

    public function test_service_show_commission_rule_shape_has_all_required_fields(): void
    {
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => $this->service->id,
            'type' => 'percentage',
            'value' => 15.00,
            'is_active' => true,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('commission_rules.0', fn ($rule) => $rule
                ->has('id')
                ->has('scope_type')
                ->has('type')
                ->has('value')
                ->has('is_active')
                ->has('employee')
                ->has('effective_from')
                ->has('effective_until')
            )
        );
    }

    public function test_service_show_inactive_commission_rule_is_included(): void
    {
        CommissionRule::factory()->inactive()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => $this->service->id,
            'type' => 'percentage',
            'value' => 10.00,
            'effective_from' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get("/services/{$this->service->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('commission_rules', 1)
            ->where('commission_rules.0.is_active', false)
        );
    }
}
