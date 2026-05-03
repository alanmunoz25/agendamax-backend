<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Exceptions\ElectronicInvoice\NcfRangeExhaustedException;
use App\Exceptions\ElectronicInvoice\NcfRangeExpiredException;
use App\Models\Business;
use App\Models\NcfRango;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcfRangoTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_sequential_ncf_correctly(): void
    {
        $rango = NcfRango::factory()->create([
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 1,
        ]);

        $encf = $rango->assignNextSecuencial();

        $this->assertEquals('E310000000001', $encf);
        $this->assertEquals(13, strlen($encf));
    }

    public function test_ecf_example_e310000000003(): void
    {
        $rango = NcfRango::factory()->create([
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 3,
        ]);

        $encf = $rango->assignNextSecuencial();

        $this->assertEquals('E310000000003', $encf);
    }

    public function test_increments_proximo_secuencial(): void
    {
        $rango = NcfRango::factory()->create([
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 1,
        ]);

        $rango->assignNextSecuencial();

        $this->assertDatabaseHas('ncf_rangos', [
            'id' => $rango->id,
            'proximo_secuencial' => 2,
        ]);
    }

    public function test_throws_when_exhausted(): void
    {
        $rango = NcfRango::factory()->exhausted()->create([
            'tipo_ecf' => 31,
        ]);

        $this->expectException(NcfRangeExhaustedException::class);

        $rango->assignNextSecuencial();
    }

    public function test_marks_exhausted_when_last_sequential_used(): void
    {
        $rango = NcfRango::factory()->create([
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

    public function test_throws_when_expired(): void
    {
        $rango = NcfRango::factory()->create([
            'tipo_ecf' => 31,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $this->expectException(NcfRangeExpiredException::class);

        $rango->assignNextSecuencial();
    }

    public function test_business_isolation(): void
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

    public function test_multiple_ranges_same_type_only_one_active(): void
    {
        $business = Business::factory()->create();

        $exhaustedRango = NcfRango::factory()->exhausted()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 100,
        ]);

        $activeRango = NcfRango::factory()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 31,
            'secuencia_desde' => 101,
            'secuencia_hasta' => 200,
            'proximo_secuencial' => 101,
        ]);

        $found = NcfRango::withoutGlobalScopes()
            ->active()
            ->forBusiness($business->id)
            ->forTipo(31)
            ->first();

        $this->assertNotNull($found);
        $this->assertEquals($activeRango->id, $found->id);

        $encf = $found->assignNextSecuencial();
        $this->assertEquals('E310000000101', $encf);
    }

    public function test_concurrent_assignment_no_duplicates(): void
    {
        $rango = NcfRango::factory()->create([
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 1,
        ]);

        // Simulate two concurrent calls by calling assignNextSecuencial twice
        // on fresh instances of the same record
        $instance1 = NcfRango::withoutGlobalScopes()->find($rango->id);
        $instance2 = NcfRango::withoutGlobalScopes()->find($rango->id);

        $encf1 = $instance1->assignNextSecuencial();
        $encf2 = $instance2->assignNextSecuencial();

        $this->assertNotEquals($encf1, $encf2);
        $this->assertEquals('E310000000001', $encf1);
        $this->assertEquals('E310000000002', $encf2);

        $this->assertDatabaseHas('ncf_rangos', [
            'id' => $rango->id,
            'proximo_secuencial' => 3,
        ]);
    }

    public function test_remaining_count_correct(): void
    {
        $rango = NcfRango::factory()->create([
            'secuencia_desde' => 1,
            'secuencia_hasta' => 100,
            'proximo_secuencial' => 1,
        ]);

        $this->assertEquals(100, $rango->remainingCount());

        $rango->assignNextSecuencial();
        $rango->refresh();

        $this->assertEquals(99, $rango->remainingCount());
    }

    public function test_all_ecf_types_31_to_47(): void
    {
        $validTypes = [31, 32, 33, 34, 41, 43, 44, 45, 46, 47];

        foreach ($validTypes as $tipo) {
            $rango = NcfRango::factory()->create([
                'tipo_ecf' => $tipo,
                'secuencia_desde' => 1,
                'secuencia_hasta' => 1000,
                'proximo_secuencial' => 1,
            ]);

            $expectedPrefix = 'E'.str_pad((string) $tipo, 2, '0', STR_PAD_LEFT);
            $formatted = $rango->formatEcf(1);

            $this->assertStringStartsWith($expectedPrefix, $formatted, "Failed for tipo {$tipo}");
            $this->assertEquals(13, strlen($formatted), "eNCF for tipo {$tipo} must be exactly 13 chars");
        }
    }
}
