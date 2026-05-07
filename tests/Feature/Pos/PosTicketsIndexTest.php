<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTicketsIndexTest extends TestCase
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

    public function test_tickets_index_returns_200_for_authenticated_business_admin(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertStatus(200);
    }

    public function test_tickets_index_renders_correct_inertia_component(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertInertia(fn ($page) => $page->component('Pos/Tickets/Index'));
    }

    public function test_tickets_index_returns_paginated_list(): void
    {
        PosTicket::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Pos/Tickets/Index')
                ->has('tickets')
                ->has('tickets.data', 5)
                ->has('tickets.current_page')
                ->has('tickets.last_page')
                ->has('tickets.per_page')
                ->has('tickets.total');
        });
    }

    public function test_tickets_index_returns_empty_list_when_no_tickets(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Pos/Tickets/Index')
                ->has('tickets.data', 0);
        });
    }

    public function test_tickets_index_does_not_leak_other_business_tickets(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherCashier = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
        ]);

        PosTicket::factory()->count(3)->create([
            'business_id' => $otherBusiness->id,
            'cashier_id' => $otherCashier->id,
        ]);

        PosTicket::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Pos/Tickets/Index')
                ->has('tickets.data', 2);
        });
    }

    public function test_tickets_index_filters_by_search_term(): void
    {
        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'ticket_number' => 'TKT-2025-0001',
            'client_name' => 'Ana García',
        ]);

        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'ticket_number' => 'TKT-2025-0002',
            'client_name' => 'Pedro López',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index', ['search' => 'Ana']));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Pos/Tickets/Index')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.client_name', 'Ana García');
        });
    }

    public function test_tickets_index_filters_by_date(): void
    {
        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'created_at' => '2025-01-15 10:00:00',
        ]);

        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'created_at' => '2025-03-20 10:00:00',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index', ['date' => '2025-01-15']));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Pos/Tickets/Index')
                ->has('tickets.data', 1);
        });
    }

    public function test_unauthenticated_user_is_redirected_from_tickets_index(): void
    {
        $response = $this->get(route('pos.tickets.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_super_admin_without_business_id_receives_422_on_tickets_index(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertStatus(422);
    }

    public function test_super_admin_with_business_id_can_access_tickets_index(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin);

        $response = $this->get(route('pos.tickets.index', ['business_id' => $this->business->id]));

        $response->assertStatus(200);
    }
}
