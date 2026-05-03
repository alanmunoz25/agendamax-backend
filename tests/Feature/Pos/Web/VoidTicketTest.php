<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoidTicketTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);
    }

    public function test_can_void_paid_ticket(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticket), [
            'reason' => 'Cliente solicitó corrección de servicios.',
        ]);

        $response->assertRedirect(route('pos.tickets.show', $ticket));
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('voided', $ticket->status);
        $this->assertNotNull($ticket->voided_at);
        $this->assertEquals($this->cashier->id, $ticket->voided_by);
    }

    public function test_void_requires_reason(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticket), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_void_reason_minimum_length(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticket), [
            'reason' => 'corto',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_void_restores_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'completed',
        ]);

        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'appointment_id' => $appointment->id,
            'status' => 'paid',
        ]);

        $appointment->update(['ticket_id' => $ticket->id]);

        $this->actingAs($this->cashier);

        $this->post(route('pos.tickets.void', $ticket), [
            'reason' => 'Anulación por error en el cobro.',
        ]);

        $appointment->refresh();
        $this->assertNull($appointment->ticket_id);
        $this->assertEquals('completed', $appointment->status);
    }

    public function test_cannot_void_already_voided_ticket(): void
    {
        $ticket = PosTicket::factory()->voided()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticket), [
            'reason' => 'Intento de anulación duplicada.',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_void_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();
        $cashierB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'business_admin',
        ]);

        $ticketB = PosTicket::factory()->create([
            'business_id' => $businessB->id,
            'cashier_id' => $cashierB->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.tickets.void', $ticketB), [
            'reason' => 'Intentando anular ticket de otro negocio.',
        ]);

        $response->assertStatus(404);

        $ticketB->refresh();
        $this->assertEquals('paid', $ticketB->status);
    }
}
