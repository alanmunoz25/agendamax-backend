<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Ecf;
use App\Models\PosShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentRegression2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * BLOCK-007: role cannot be mass assigned on User.
     */
    public function test_user_role_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'secret',
            'role' => 'super_admin', // guarded — should be ignored
        ]);

        $this->assertNull($user->role);
    }

    /**
     * BLOCK-007: business_id cannot be mass assigned on User.
     */
    public function test_user_business_id_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'secret',
            'business_id' => 999, // guarded — should be ignored
        ]);

        $this->assertNull($user->business_id);
    }

    /**
     * BLOCK-008: status cannot be mass assigned on Ecf.
     */
    public function test_ecf_status_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();

        $ecf = new Ecf;
        $ecf->fill([
            'business_id' => $business->id,
            'numero_ecf' => 'B0100000001',
            'tipo' => '31',
            'fecha_emision' => now()->toDateString(),
            'monto_total' => '1000.00',
            'itbis_total' => '180.00',
            'monto_gravado' => '1000.00',
            'status' => 'accepted', // guarded — should be ignored
        ]);

        $this->assertNull($ecf->status);
    }

    /**
     * BLOCK-008: track_id cannot be mass assigned on Ecf.
     */
    public function test_ecf_track_id_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();

        $ecf = new Ecf;
        $ecf->fill([
            'business_id' => $business->id,
            'numero_ecf' => 'B0100000001',
            'tipo' => '31',
            'fecha_emision' => now()->toDateString(),
            'monto_total' => '1000.00',
            'itbis_total' => '180.00',
            'monto_gravado' => '1000.00',
            'track_id' => 'fake-track-id', // guarded — should be ignored
        ]);

        $this->assertNull($ecf->track_id);
    }

    /**
     * BLOCK-009: total_sales cannot be mass assigned on PosShift.
     */
    public function test_pos_shift_total_sales_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'business_admin',
        ]);

        $shift = new PosShift;
        $shift->fill([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'shift_date' => now()->toDateString(),
            'opening_cash' => '5000.00',
            'total_sales' => '99999.00', // guarded — should be ignored
        ]);

        $this->assertNull($shift->total_sales);
    }

    /**
     * BLOCK-009: cash_difference cannot be mass assigned on PosShift.
     */
    public function test_pos_shift_cash_difference_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'business_admin',
        ]);

        $shift = new PosShift;
        $shift->fill([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'shift_date' => now()->toDateString(),
            'opening_cash' => '5000.00',
            'cash_difference' => '-9999.00', // guarded — should be ignored
        ]);

        $this->assertNull($shift->cash_difference);
    }
}
