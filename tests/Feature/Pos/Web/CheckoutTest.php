<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Jobs\EmitEcfJob;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PosTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['pos_commissions_enabled' => false]);

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
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'appointment_id' => null,
            'client_name' => 'Walk-in Test',
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
        ], $overrides);
    }

    public function test_checkout_creates_ticket_and_redirects(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload());

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pos_tickets', [
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);
    }

    public function test_checkout_from_appointment_updates_appointment(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'client_id' => $client->id,
            'employee_id' => $this->employee->id,
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload([
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
        ]));

        $response->assertRedirect(route('pos.index'));

        $appointment->refresh();
        $this->assertNotNull($appointment->ticket_id);
        $this->assertEquals('completed', $appointment->status);
    }

    public function test_checkout_with_mixed_payments(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload([
            'payments' => [
                ['method' => 'cash', 'amount' => '500.00'],
                ['method' => 'card', 'amount' => '503.00', 'reference' => 'AUTH-9999'],
            ],
        ]));

        $response->assertRedirect(route('pos.index'));

        $ticket = PosTicket::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertCount(2, $ticket->payments);
    }

    public function test_checkout_with_tip(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload([
            'tip_amount' => '127.50',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertRedirect(route('pos.index'));

        $ticket = PosTicket::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('127.50', $ticket->tip_amount);
    }

    public function test_checkout_with_ecf_on_dispatches_job(): void
    {
        Bus::fake();

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => true,
            'ecf_type' => 'consumidor_final',
        ]));

        Bus::assertDispatched(EmitEcfJob::class);
    }

    public function test_checkout_with_ecf_off_does_not_dispatch_job(): void
    {
        Bus::fake();

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.store'), $this->basePayload([
            'ecf_requested' => false,
        ]));

        Bus::assertNotDispatched(EmitEcfJob::class);
    }

    public function test_checkout_double_cobro_rejected(): void
    {
        $existingTicket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'completed',
            'ticket_id' => $existingTicket->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload([
            'appointment_id' => $appointment->id,
        ]));

        $response->assertSessionHasErrors('appointment_id');
    }

    public function test_checkout_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();

        $cashierB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'business_admin',
        ]);

        $appointmentB = Appointment::factory()->create([
            'business_id' => $businessB->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload([
            'appointment_id' => $appointmentB->id,
        ]));

        $response->assertSessionHasErrors('appointment_id');
        $this->assertEquals(0, PosTicket::withoutGlobalScopes()->count());
    }

    public function test_checkout_requires_at_least_one_item(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), $this->basePayload([
            'items' => [],
        ]));

        $response->assertSessionHasErrors('items');
    }
}
