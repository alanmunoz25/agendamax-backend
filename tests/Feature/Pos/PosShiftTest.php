<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosShiftTest extends TestCase
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

    /** @test */
    public function test_can_create_shift(): void
    {
        $this->actingAs($this->cashier);

        $today = now()->toDateString();

        $response = $this->post(route('pos.shift.store'), [
            'cashier_id' => $this->cashier->id,
            'shift_date' => $today,
            'opened_at' => '09:00',
            'closed_at' => '18:00',
            'opening_cash' => '500.00',
            'closing_cash_counted' => '500.00',
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHas('success');

        $shift = PosShift::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('cashier_id', $this->cashier->id)
            ->first();

        $this->assertNotNull($shift);
        $this->assertEquals($today, $shift->shift_date->toDateString());
    }

    /** @test */
    public function test_prevents_duplicate_shift_same_day(): void
    {
        $today = now()->toDateString();

        PosShift::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'shift_date' => $today,
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.shift.store'), [
            'cashier_id' => $this->cashier->id,
            'shift_date' => $today,
            'opened_at' => '09:00',
            'closed_at' => '18:00',
            'opening_cash' => '500.00',
            'closing_cash_counted' => '500.00',
        ]);

        $response->assertSessionHasErrors('shift_date');
        $this->assertEquals(1, PosShift::withoutGlobalScopes()->where('business_id', $this->business->id)->count());
    }

    /** @test */
    public function test_shift_summary_calculates_correctly(): void
    {
        $today = now()->toDateString();

        // Create paid tickets for today
        $ticket1 = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
            'total' => '1000.00',
            'tip_amount' => '100.00',
            'created_at' => now(),
        ]);

        PosPayment::factory()->create([
            'pos_ticket_id' => $ticket1->id,
            'method' => 'cash',
            'amount' => '1000.00',
        ]);

        $ticket2 = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
            'total' => '500.00',
            'tip_amount' => '50.00',
            'created_at' => now(),
        ]);

        PosPayment::factory()->create([
            'pos_ticket_id' => $ticket2->id,
            'method' => 'card',
            'amount' => '500.00',
        ]);

        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.shift.store'), [
            'cashier_id' => $this->cashier->id,
            'shift_date' => $today,
            'opened_at' => '09:00',
            'closed_at' => '18:00',
            'opening_cash' => '200.00',
            'closing_cash_counted' => '1200.00',
        ]);

        $response->assertRedirect(route('pos.index'));

        $shift = PosShift::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($shift);
        $this->assertEquals(2, $shift->tickets_count);
        $this->assertEquals('1500.00', $shift->total_sales);
        $this->assertEquals('1000.00', $shift->cash_sales);
        $this->assertEquals('500.00', $shift->card_sales);
        $this->assertEquals('150.00', $shift->total_tips);
    }
}
