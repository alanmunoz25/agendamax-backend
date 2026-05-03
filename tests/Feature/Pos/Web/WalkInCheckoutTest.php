<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PosTicket;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkInCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    private Service $service;

    private Product $product;

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
            'price' => '850.00',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'business_id' => $this->business->id,
            'price' => '380.00',
            'is_active' => true,
        ]);
    }

    public function test_walkin_without_appointment_creates_ticket(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Cliente Pasillo',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00', 'cash_tendered' => '1100.00'],
            ],
            'ecf_requested' => false,
        ]);

        $response->assertRedirect(route('pos.index'));

        $ticket = PosTicket::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->appointment_id);
        $this->assertEquals('Cliente Pasillo', $ticket->client_name);
        $this->assertEquals('paid', $ticket->status);
    }

    public function test_walkin_without_client_name_uses_default(): void
    {
        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => null,
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00'],
            ],
            'ecf_requested' => false,
        ]);

        $ticket = PosTicket::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->appointment_id);
    }

    public function test_walkin_with_product_item(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Cliente Pasillo',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'product',
                    'item_id' => $this->product->id,
                    'name' => $this->product->name,
                    'unit_price' => '380.00',
                    'qty' => 2,
                    'employee_id' => null,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'card', 'amount' => '896.80'],
            ],
            'ecf_requested' => false,
        ]);

        $response->assertRedirect(route('pos.index'));

        $ticket = PosTicket::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertCount(1, $ticket->items);
        $this->assertEquals('product', $ticket->items->first()->item_type);
        $this->assertEquals(2, $ticket->items->first()->qty);
    }

    public function test_walkin_generates_commission_when_enabled(): void
    {
        $this->business->update(['pos_commissions_enabled' => true]);

        PayrollPeriod::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'open',
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
        ]);

        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => '10.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Cliente Walk-in',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00'],
            ],
            'ecf_requested' => false,
        ]);

        $this->assertDatabaseHas('commission_records', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_walkin_does_not_generate_commission_when_disabled(): void
    {
        $this->business->update(['pos_commissions_enabled' => false]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Cliente Walk-in',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00'],
            ],
            'ecf_requested' => false,
        ]);

        $this->assertEquals(0, CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->count()
        );
    }

    public function test_walkin_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();

        $employeeB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'employee',
        ]);

        $employeeRecordB = Employee::factory()->create([
            'business_id' => $businessB->id,
            'user_id' => $employeeB->id,
        ]);

        $this->actingAs($this->cashier);

        // Attempt to assign an employee from business B
        $response = $this->post(route('pos.tickets.store'), [
            'appointment_id' => null,
            'client_name' => 'Cliente Cross-tenant',
            'employee_id' => $employeeRecordB->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $employeeRecordB->id,
                    'appointment_service_id' => null,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00'],
            ],
            'ecf_requested' => false,
        ]);

        $response->assertSessionHasErrors('employee_id');
    }
}
