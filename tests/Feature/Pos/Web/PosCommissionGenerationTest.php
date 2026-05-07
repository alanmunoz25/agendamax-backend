<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosCommissionGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['pos_commissions_enabled' => true]);

        $this->cashier = User::factory()->create([
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

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => '1000.00',
            'is_active' => true,
        ]);

        // Open payroll period for commission allocation
        PayrollPeriod::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'open',
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
        ]);

        // Default percentage commission rule
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => '10.00',
            'is_active' => true,
        ]);
    }

    public function test_checkout_from_appointment_generates_commission(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        // Enroll client in the business via the pivot table.
        DB::table('user_business')->insertOrIgnore([
            'user_id' => $client->id,
            'business_id' => $this->business->id,
            'role_in_business' => 'client',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $client->id,
            'employee_id' => $this->employee->id,
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        // Attach service to appointment via pivot so CommissionService can process the line
        $appointment->services()->attach($this->service->id, [
            'employee_id' => $this->employee->id,
        ]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '1000.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '0',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'card', 'amount' => '1000.00'],
            ],
            'ecf_requested' => false,
        ]);

        $this->assertDatabaseHas('commission_records', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'appointment_id' => $appointment->id,
        ]);
    }

    public function test_walkin_checkout_generates_commission_when_enabled(): void
    {
        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Walk-in Client',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '1000.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '0',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1000.00'],
            ],
            'ecf_requested' => false,
        ]);

        $commission = CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('employee_id', $this->employee->id)
            ->whereNull('appointment_id')
            ->first();

        $this->assertNotNull($commission);
        $this->assertEquals('100.00', $commission->commission_amount);
    }

    public function test_walkin_checkout_no_commission_when_disabled(): void
    {
        $this->business->update(['pos_commissions_enabled' => false]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Walk-in Client',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '1000.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '0',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1000.00'],
            ],
            'ecf_requested' => false,
        ]);

        $this->assertEquals(0, CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->count()
        );
    }

    public function test_commission_not_generated_for_product_items(): void
    {
        $this->actingAs($this->cashier);

        $product = \App\Models\Product::factory()->create([
            'business_id' => $this->business->id,
            'price' => '500.00',
        ]);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Walk-in Client',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'product',
                    'item_id' => $product->id,
                    'name' => $product->name,
                    'unit_price' => '500.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '0',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '500.00'],
            ],
            'ecf_requested' => false,
        ]);

        // Product items do not generate commissions (only service items do)
        $this->assertEquals(0, CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->count()
        );
    }

    public function test_commission_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create(['pos_commissions_enabled' => true]);
        $cashierB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'business_admin',
        ]);

        $employeeUserB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'employee',
        ]);

        $employeeB = Employee::factory()->create([
            'business_id' => $businessB->id,
            'user_id' => $employeeUserB->id,
        ]);

        PayrollPeriod::factory()->create([
            'business_id' => $businessB->id,
            'status' => 'open',
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
        ]);

        CommissionRule::factory()->create([
            'business_id' => $businessB->id,
            'type' => 'percentage',
            'value' => '10.00',
            'is_active' => true,
        ]);

        $serviceB = Service::factory()->create([
            'business_id' => $businessB->id,
            'price' => '1000.00',
        ]);

        $this->actingAs($cashierB);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Client B',
            'employee_id' => $employeeB->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $serviceB->id,
                    'name' => $serviceB->name,
                    'unit_price' => '1000.00',
                    'qty' => 1,
                    'employee_id' => $employeeB->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '0',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1000.00'],
            ],
            'ecf_requested' => false,
        ]);

        // Business A should have no commissions; Business B should have 1
        $this->assertEquals(0, CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->count()
        );

        $this->assertEquals(1, CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $businessB->id)
            ->count()
        );
    }
}
