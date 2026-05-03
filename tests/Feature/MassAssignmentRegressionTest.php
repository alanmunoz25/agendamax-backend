<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that BusinessFeConfig status-like fields are protected from mass assignment.
     */
    public function test_business_fe_config_status_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();

        $config = new BusinessFeConfig;
        $config->fill([
            'business_id' => $business->id,
            'rnc_emisor' => '101000001',
            'razon_social' => 'Test SRL',
            'activo' => true, // guarded — should be ignored
        ]);

        // activo is not fillable, so it should NOT be set via fill()
        $this->assertNull($config->activo);
    }

    /**
     * Verify that ambiente cannot be mass assigned on BusinessFeConfig.
     */
    public function test_business_fe_config_ambiente_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();

        $config = new BusinessFeConfig;
        $config->fill([
            'business_id' => $business->id,
            'rnc_emisor' => '101000001',
            'razon_social' => 'Test SRL',
            'ambiente' => 'ECF', // guarded — should be ignored
        ]);

        // ambiente is not fillable, so it should NOT be set via fill()
        $this->assertNull($config->ambiente);
    }

    /**
     * Verify that certificado_digital cannot be mass assigned on BusinessFeConfig.
     */
    public function test_business_fe_config_certificate_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();

        $config = new BusinessFeConfig;
        $config->fill([
            'business_id' => $business->id,
            'rnc_emisor' => '101000001',
            'razon_social' => 'Test SRL',
            'certificado_digital' => base64_encode('fake-cert'),
        ]);

        $this->assertNull($config->certificado_digital);
    }

    /**
     * Verify that PosTicket status cannot be mass assigned.
     */
    public function test_pos_ticket_status_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id, 'role' => 'business_admin']);

        $ticket = new PosTicket;
        $ticket->fill([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'ticket_number' => 'TKT-2026-0001',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'itbis_pct' => 18,
            'itbis_amount' => 180,
            'tip_amount' => 0,
            'total' => 1180,
            'status' => 'voided', // guarded — should be ignored
        ]);

        $this->assertNull($ticket->status);
    }

    /**
     * Verify that PosTicket void metadata cannot be mass assigned.
     */
    public function test_pos_ticket_void_metadata_cannot_be_mass_assigned(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id, 'role' => 'business_admin']);

        $ticket = new PosTicket;
        $ticket->fill([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'ticket_number' => 'TKT-2026-0001',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'itbis_pct' => 18,
            'itbis_amount' => 180,
            'tip_amount' => 0,
            'total' => 1180,
            'voided_by' => 999,    // guarded
            'voided_at' => now(),  // guarded
            'void_reason' => 'hack attempt', // guarded
            'ecf_ncf' => 'B01FAKE001', // guarded
        ]);

        $this->assertNull($ticket->voided_by);
        $this->assertNull($ticket->voided_at);
        $this->assertNull($ticket->void_reason);
        $this->assertNull($ticket->ecf_ncf);
    }
}
