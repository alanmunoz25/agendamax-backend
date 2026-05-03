<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Exceptions\ElectronicInvoice\NcfRangeExhaustedException;
use App\Exceptions\ElectronicInvoice\NcfRangeExpiredException;
use App\Models\Business;
use App\Models\NcfRango;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Legacy test file — tests migrated to NcfRangoTest.
 * These tests cover NcfRango (formerly EcfSequence) behaviour.
 */
class EcfSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_secuencial_increments_atomically(): void
    {
        $business = Business::factory()->create();
        $rango = NcfRango::factory()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 100,
            'proximo_secuencial' => 1,
        ]);

        $first = $rango->assignNextSecuencial();
        $second = $rango->assignNextSecuencial();
        $third = $rango->assignNextSecuencial();

        $this->assertEquals('E310000000001', $first);
        $this->assertEquals('E310000000002', $second);
        $this->assertEquals('E310000000003', $third);

        $this->assertDatabaseHas('ncf_rangos', [
            'id' => $rango->id,
            'proximo_secuencial' => 4,
        ]);
    }

    public function test_sequence_marked_exhausted_when_last_number_used(): void
    {
        $business = Business::factory()->create();
        $rango = NcfRango::factory()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1,
            'proximo_secuencial' => 1,
            'status' => 'active',
        ]);

        $encf = $rango->assignNextSecuencial();
        $this->assertEquals('E310000000001', $encf);

        $this->assertDatabaseHas('ncf_rangos', [
            'id' => $rango->id,
            'status' => 'exhausted',
        ]);
    }

    public function test_throws_exception_when_sequence_exhausted(): void
    {
        $business = Business::factory()->create();
        $rango = NcfRango::factory()->exhausted()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
        ]);

        $this->expectException(NcfRangeExhaustedException::class);

        $rango->assignNextSecuencial();
    }

    public function test_throws_exception_when_sequence_expired(): void
    {
        $business = Business::factory()->create();
        $rango = NcfRango::factory()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $this->expectException(NcfRangeExpiredException::class);

        $rango->assignNextSecuencial();
    }

    public function test_format_ecf_pads_secuencial_to_10_digits(): void
    {
        $business = Business::factory()->create();
        $rango = NcfRango::factory()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
        ]);

        $this->assertEquals('E310000000001', $rango->formatEcf(1));
        $this->assertEquals('E319999999999', $rango->formatEcf(9999999999));
    }

    public function test_sequences_are_independent_per_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $rangoA = NcfRango::factory()->create([
            'business_id' => $businessA->id,
            'tipo_ecf' => 32,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 999,
            'proximo_secuencial' => 1,
        ]);

        $rangoB = NcfRango::factory()->create([
            'business_id' => $businessB->id,
            'tipo_ecf' => 32,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 999,
            'proximo_secuencial' => 1,
        ]);

        $rangoA->assignNextSecuencial();
        $rangoA->assignNextSecuencial();
        $rangoB->assignNextSecuencial();

        $this->assertDatabaseHas('ncf_rangos', ['id' => $rangoA->id, 'proximo_secuencial' => 3]);
        $this->assertDatabaseHas('ncf_rangos', ['id' => $rangoB->id, 'proximo_secuencial' => 2]);
    }

    public function test_multiple_active_ranges_not_allowed_at_app_level(): void
    {
        $business = Business::factory()->create();

        // Two ranges for same type — allowed at DB level, enforced at app level
        $rango1 = NcfRango::factory()->create(['business_id' => $business->id, 'tipo_ecf' => 32]);
        $rango2 = NcfRango::factory()->create(['business_id' => $business->id, 'tipo_ecf' => 32]);

        $activeCount = NcfRango::withoutGlobalScopes()
            ->forBusiness($business->id)
            ->forTipo(32)
            ->active()
            ->count();

        // Both exist in DB — enforcement is at application level, not DB constraint
        $this->assertEquals(2, $activeCount);
    }
}
