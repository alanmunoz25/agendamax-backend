<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    private Business $business1;

    private Business $business2;

    private User $cashier1;

    private User $cashier2;

    private Employee $employee1;

    private Service $service1;

    private Product $product1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business1 = Business::factory()->create(['pos_commissions_enabled' => true]);
        $this->business2 = Business::factory()->create(['pos_commissions_enabled' => true]);

        $this->cashier1 = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business1->id,
        ]);

        $this->cashier2 = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business2->id,
        ]);

        $employeeUser1 = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business1->id,
        ]);

        $this->employee1 = Employee::factory()->create([
            'business_id' => $this->business1->id,
            'user_id' => $employeeUser1->id,
            'is_active' => true,
        ]);

        $this->service1 = Service::factory()->create([
            'business_id' => $this->business1->id,
            'price' => 1000,
            'is_active' => true,
        ]);

        $this->product1 = Product::factory()->create([
            'business_id' => $this->business1->id,
            'price' => 500,
            'is_active' => true,
        ]);
    }

    /**
     * A cashier from business1 cannot reference an appointment from business2.
     */
    public function test_pos_ticket_cannot_reference_appointment_of_other_business(): void
    {
        // Create appointment belonging to business2
        $clientB2 = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->business2->id,
        ]);

        $employeeB2 = Employee::factory()->create(['business_id' => $this->business2->id]);
        $serviceB2 = Service::factory()->create(['business_id' => $this->business2->id]);

        $appointmentB2 = Appointment::factory()->create([
            'business_id' => $this->business2->id,
            'client_id' => $clientB2->id,
            'employee_id' => $employeeB2->id,
            'service_id' => $serviceB2->id,
        ]);

        $payload = [
            'appointment_id' => $appointmentB2->id, // belongs to business2, not business1
            'client_name' => 'Walk-in',
            'client_rnc' => null,
            'employee_id' => null,
            'discount_amount' => 0,
            'itbis_pct' => 18,
            'tip_amount' => 0,
            'ecf_requested' => false,
            'ecf_type' => null,
            'notes' => null,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service1->id,
                    'name' => 'Corte',
                    'unit_price' => 1000,
                    'qty' => 1,
                    'employee_id' => null,
                    'appointment_service_id' => null,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 1180, 'reference' => null, 'cash_tendered' => 1200],
            ],
        ];

        $this->actingAs($this->cashier1)
            ->post(route('pos.tickets.store'), $payload)
            ->assertSessionHasErrors('appointment_id');
    }

    /**
     * StorePosShiftRequest forces cashier_id to the authenticated user's ID,
     * regardless of what the client submits.
     */
    public function test_pos_shift_cashier_id_is_forced_from_auth(): void
    {
        $payload = [
            'cashier_id' => $this->cashier2->id, // attacker tries to forge cashier_id
            'shift_date' => now()->toDateString(),
            'opened_at' => '09:00',
            'closing_cash_counted' => 1500,
            'opening_cash' => 500,
            'difference_reason' => null,
        ];

        $response = $this->actingAs($this->cashier1)
            ->post(route('pos.shift.store'), $payload);

        // The shift stored should use the authenticated user's ID, not the forged one
        $this->assertDatabaseHas('pos_shifts', [
            'cashier_id' => $this->cashier1->id,
        ]);

        $this->assertDatabaseMissing('pos_shifts', [
            'cashier_id' => $this->cashier2->id,
        ]);
    }
}
