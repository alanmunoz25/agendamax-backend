<?php

declare(strict_types=1);

namespace Tests\Feature\Pos\Web;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #1 Sprint 6 QA — POS "Citas del día" visibility.
 *
 * Verifies that:
 * - Today's pending appointments are visible.
 * - Today's already-billed (ticket_id set) appointments are also visible.
 * - Yesterday's billed appointments are visible (billed today edge-case).
 * - Old uncollected appointments (beyond yesterday) are NOT visible.
 */
class PosIndexTodayAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

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
    }

    public function test_pending_appointment_today_is_visible(): void
    {
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->startOfDay()->addHours(10),
            'status' => 'pending',
            'ticket_id' => null,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 1)
        );
    }

    public function test_billed_appointment_today_is_visible(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->startOfDay()->addHours(9),
            'status' => 'completed',
            'ticket_id' => $ticket->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 1)
        );
    }

    public function test_billed_appointment_from_yesterday_is_visible(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        // Appointment was scheduled yesterday but was billed (has ticket)
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->subDay()->startOfDay()->addHours(14),
            'status' => 'completed',
            'ticket_id' => $ticket->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 1)
        );
    }

    public function test_uncollected_appointment_from_yesterday_is_not_visible(): void
    {
        // Yesterday's appointment without ticket — should NOT appear in today's list
        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->subDay()->startOfDay()->addHours(11),
            'status' => 'completed',
            'ticket_id' => null,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 0)
        );
    }

    public function test_old_appointment_from_two_days_ago_is_not_visible_even_if_billed(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->subDays(2)->startOfDay()->addHours(10),
            'status' => 'completed',
            'ticket_id' => $ticket->id,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 0)
        );
    }

    public function test_today_summary_collected_count_includes_billed_appointments(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->startOfDay()->addHours(9),
            'status' => 'completed',
            'ticket_id' => $ticket->id,
        ]);

        Appointment::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'scheduled_at' => now()->startOfDay()->addHours(11),
            'status' => 'pending',
            'ticket_id' => null,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->get(route('pos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pos/Index')
            ->has('today_appointments', 2)
            ->where('today_summary.collected_count', 1)
            ->where('today_summary.uncollected_count', 1)
        );
    }
}
