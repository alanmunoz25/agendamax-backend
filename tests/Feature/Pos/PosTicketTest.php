<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PosTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTicketTest extends TestCase
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
            'price' => 850,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_can_create_ticket_from_appointment(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'client_id' => $client->id,
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), [
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
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
                ['method' => 'card', 'amount' => '1003.00'],
            ],
            'ecf_requested' => false,
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHas('success');

        $ticket = PosTicket::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('paid', $ticket->status);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);

        // Appointment updated
        $appointment->refresh();
        $this->assertEquals($ticket->id, $appointment->ticket_id);
        $this->assertEquals('completed', $appointment->status);
    }

    /** @test */
    public function test_prevents_double_checkout(): void
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

        $response = $this->post(route('pos.tickets.store'), [
            'appointment_id' => $appointment->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
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

        $response->assertSessionHasErrors('appointment_id');
        $this->assertEquals(1, PosTicket::withoutGlobalScopes()->where('business_id', $this->business->id)->count());
    }

    /** @test */
    public function test_can_void_paid_ticket(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'appointment_id' => $appointment->id,
            'status' => 'paid',
        ]);

        $appointment->update(['ticket_id' => $ticket->id]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticket), [
            'reason' => 'Cliente solicitó corrección de servicios cobrados.',
        ]);

        $response->assertRedirect(route('pos.tickets.show', $ticket));

        $ticket->refresh();
        $this->assertEquals('voided', $ticket->status);
        $this->assertNotNull($ticket->voided_at);

        $appointment->refresh();
        $this->assertNull($appointment->ticket_id);
        $this->assertEquals('completed', $appointment->status);
    }

    /** @test */
    public function test_cannot_void_already_voided_ticket(): void
    {
        $ticket = PosTicket::factory()->voided()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticket), [
            'reason' => 'Intento de double void para testing.',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    /** @test */
    public function test_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();

        $cashierB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'business_admin',
        ]);

        $appointmentB = Appointment::factory()->create([
            'business_id' => $businessB->id,
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        // Cashier from Business A tries to checkout appointment from Business B
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.store'), [
            'appointment_id' => $appointmentB->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '500.00',
                    'qty' => 1,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '590.00'],
            ],
            'ecf_requested' => false,
        ]);

        // Should fail with a validation error — Rule::exists scoped to business_id
        // rejects appointments that belong to a different business.
        $response->assertSessionHasErrors('appointment_id');
        $this->assertEquals(0, PosTicket::withoutGlobalScopes()->count());
    }
}
