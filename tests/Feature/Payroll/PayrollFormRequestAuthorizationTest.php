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

/**
 * Authorization tests for payroll FormRequests.
 *
 * Covers StoreCommissionRuleRequest, UpdateCommissionRuleRequest, and UpdateBaseSalaryRequest
 * ensuring employee/client/lead roles are denied while super_admin and business_admin are allowed.
 */
class PayrollFormRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $superAdmin;

    private User $businessAdmin;

    private User $employeeUser;

    private Employee $employeeRecord;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'business_id' => null,
        ]);

        $this->businessAdmin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
            'email_verified_at' => now(),
        ]);

        $this->employeeUser = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        $this->employeeRecord = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);
    }

    // ── StoreCommissionRuleRequest ────────────────────────────────────────

    public function test_business_admin_can_store_commission_rule(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post(route('payroll.commission-rules.store'), [
                'scope_type' => 'global',
                'employee_id' => null,
                'service_id' => null,
                'type' => 'percentage',
                'value' => '10.00',
                'effective_from' => now()->toDateString(),
                'effective_until' => null,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_employee_cannot_store_commission_rule(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->post(route('payroll.commission-rules.store'), [
                'scope_type' => 'global',
                'employee_id' => null,
                'service_id' => null,
                'type' => 'percentage',
                'value' => '10.00',
                'effective_from' => now()->toDateString(),
                'effective_until' => null,
            ]);

        $response->assertStatus(403);
    }

    public function test_client_cannot_store_commission_rule(): void
    {
        $clientUser = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($clientUser)
            ->post(route('payroll.commission-rules.store'), [
                'scope_type' => 'global',
                'type' => 'percentage',
                'value' => '5.00',
                'effective_from' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
    }

    // ── UpdateCommissionRuleRequest ───────────────────────────────────────

    public function test_business_admin_can_update_commission_rule(): void
    {
        $rule = CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'percentage',
            'value' => '10.00',
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->put(route('payroll.commission-rules.update', $rule), [
                'scope_type' => 'global',
                'employee_id' => null,
                'service_id' => null,
                'type' => 'fixed',
                'value' => '500.00',
                'effective_from' => now()->toDateString(),
                'effective_until' => null,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_employee_cannot_update_commission_rule(): void
    {
        $rule = CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'type' => 'percentage',
            'value' => '10.00',
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->put(route('payroll.commission-rules.update', $rule), [
                'scope_type' => 'global',
                'type' => 'fixed',
                'value' => '500.00',
                'effective_from' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
    }

    // ── UpdateBaseSalaryRequest ───────────────────────────────────────────

    public function test_business_admin_can_update_base_salary(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->patch(route('payroll.employees.base-salary', $this->employeeRecord), [
                'base_salary' => '25000.00',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_employee_cannot_update_base_salary(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->patch(route('payroll.employees.base-salary', $this->employeeRecord), [
                'base_salary' => '25000.00',
            ]);

        $response->assertStatus(403);
    }

    public function test_lead_cannot_update_base_salary(): void
    {
        $leadUser = User::factory()->create([
            'role' => 'lead',
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($leadUser)
            ->patch(route('payroll.employees.base-salary', $this->employeeRecord), [
                'base_salary' => '25000.00',
            ]);

        $response->assertStatus(403);
    }
}
