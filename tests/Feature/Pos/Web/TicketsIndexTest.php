<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Business;
use App\Models\PosPayment;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsIndexTest extends TestCase
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

    public function test_tickets_index_renders(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Pos/Tickets/Index'));
    }

    public function test_tickets_index_lists_tickets(): void
    {
        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
            'client_name' => 'María García',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
        );
    }

    public function test_tickets_index_filter_by_search(): void
    {
        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'client_name' => 'María García',
        ]);

        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'client_name' => 'Pedro López',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index', ['search' => 'María']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
        );
    }

    public function test_tickets_index_filter_by_date(): void
    {
        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
            'created_at' => now(),
        ]);

        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
            'created_at' => now()->subDays(10),
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index', ['date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
        );
    }

    public function test_tickets_index_filter_by_payment_method(): void
    {
        $cashTicket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);
        PosPayment::factory()->create([
            'pos_ticket_id' => $cashTicket->id,
            'method' => 'cash',
        ]);

        $cardTicket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);
        PosPayment::factory()->create([
            'pos_ticket_id' => $cardTicket->id,
            'method' => 'card',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index', ['method' => 'cash']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
        );
    }

    public function test_tickets_index_multi_tenant_isolation(): void
    {
        $businessB = Business::factory()->create();
        $cashierB = User::factory()->create([
            'business_id' => $businessB->id,
            'role' => 'business_admin',
        ]);

        // Ticket belongs to business B
        PosTicket::factory()->create([
            'business_id' => $businessB->id,
            'cashier_id' => $cashierB->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 0)
        );
    }

    public function test_tickets_index_returns_filters_prop(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index', ['search' => 'test', 'method' => 'cash']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->where('filters.search', 'test')
            ->where('filters.method', 'cash')
        );
    }
}
