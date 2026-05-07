<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PosTicket;
use App\Models\PosTicketItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #2 Sprint 6 QA — /pos/tickets "Empleado" column shows all collaborators.
 *
 * Verifies that the ticket list prop includes the derived `employees` array
 * built from line items, not just the ticket-level employee_id.
 */
class PosTicketsIndexEmployeesTest extends TestCase
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

    private function makeEmployee(string $name): Employee
    {
        $user = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'name' => $name,
        ]);

        return Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_ticket_without_items_returns_empty_employees_array(): void
    {
        PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
            'client_name' => 'Test Client',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.employees', [])
        );
    }

    public function test_ticket_with_single_employee_item_returns_one_employee(): void
    {
        $employee = $this->makeEmployee('Ana López');

        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        PosTicketItem::factory()->create([
            'pos_ticket_id' => $ticket->id,
            'employee_id' => $employee->id,
            'item_type' => 'service',
            'name' => 'Corte',
            'unit_price' => '500.00',
            'qty' => 1,
            'line_total' => '500.00',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
            ->has('tickets.data.0.employees', 1)
            ->where('tickets.data.0.employees.0.name', 'Ana López')
        );
    }

    public function test_ticket_with_multiple_employees_returns_all_unique_employees(): void
    {
        $employee1 = $this->makeEmployee('Ana López');
        $employee2 = $this->makeEmployee('Beatriz Ramos');

        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        PosTicketItem::factory()->create([
            'pos_ticket_id' => $ticket->id,
            'employee_id' => $employee1->id,
            'item_type' => 'service',
            'name' => 'Corte',
            'unit_price' => '500.00',
            'qty' => 1,
            'line_total' => '500.00',
        ]);

        PosTicketItem::factory()->create([
            'pos_ticket_id' => $ticket->id,
            'employee_id' => $employee2->id,
            'item_type' => 'service',
            'name' => 'Color',
            'unit_price' => '1200.00',
            'qty' => 1,
            'line_total' => '1200.00',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
            ->has('tickets.data.0.employees', 2)
        );
    }

    public function test_ticket_items_with_same_employee_deduplicates(): void
    {
        $employee = $this->makeEmployee('Ana López');

        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        // Two items for the same employee
        PosTicketItem::factory()->create([
            'pos_ticket_id' => $ticket->id,
            'employee_id' => $employee->id,
            'item_type' => 'service',
            'name' => 'Corte',
            'unit_price' => '500.00',
            'qty' => 1,
            'line_total' => '500.00',
        ]);

        PosTicketItem::factory()->create([
            'pos_ticket_id' => $ticket->id,
            'employee_id' => $employee->id,
            'item_type' => 'service',
            'name' => 'Lavado',
            'unit_price' => '200.00',
            'qty' => 1,
            'line_total' => '200.00',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
            ->has('tickets.data.0.employees', 1)
        );
    }

    public function test_ticket_items_without_employee_are_excluded_from_employees_array(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        // Item without employee (product sale, for example)
        PosTicketItem::factory()->create([
            'pos_ticket_id' => $ticket->id,
            'employee_id' => null,
            'item_type' => 'product',
            'name' => 'Shampoo',
            'unit_price' => '350.00',
            'qty' => 1,
            'line_total' => '350.00',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.employees', [])
        );
    }
}
