<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Business;
use App\Models\PosTicket;
use App\Models\PosTicketItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsShowTest extends TestCase
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

    public function test_ticket_show_renders(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Pos/Tickets/Show'));
    }

    public function test_ticket_show_includes_items_and_payments(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        PosTicketItem::factory()->create(['pos_ticket_id' => $ticket->id]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Show')
            ->has('ticket.items')
            ->has('ticket.payments')
        );
    }

    public function test_ticket_show_includes_can_void_true_for_paid_ticket(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Show')
            ->where('can.void', true)
        );
    }

    public function test_ticket_show_includes_can_void_false_for_voided_ticket(): void
    {
        $ticket = PosTicket::factory()->voided()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Show')
            ->where('can.void', false)
        );
    }

    public function test_ticket_show_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();
        $cashierB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'business_admin',
        ]);

        $ticketB = PosTicket::factory()->create([
            'business_id' => $businessB->id,
            'cashier_id' => $cashierB->id,
        ]);

        $this->actingAs($this->cashier);

        // BelongsToBusiness scope should reject access to another business' ticket
        $response = $this->get(route('pos.tickets.show', $ticketB));

        $response->assertStatus(404);
    }
}
