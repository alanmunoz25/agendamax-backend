<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\NcfRango;
use App\Models\User;
use App\Services\ElectronicInvoice\ElectronicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EcfMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecf_query_scoped_to_authenticated_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $ecfA = Ecf::factory()->create(['business_id' => $businessA->id]);
        $ecfB = Ecf::factory()->create(['business_id' => $businessB->id]);

        $userA = User::factory()->create(['business_id' => $businessA->id, 'role' => 'business_admin']);
        $this->actingAs($userA);

        $visible = Ecf::all()->pluck('id');

        $this->assertTrue($visible->contains($ecfA->id));
        $this->assertFalse($visible->contains($ecfB->id));
    }

    public function test_ecf_received_scoped_to_authenticated_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        \App\Models\EcfReceived::create([
            'business_id' => $businessA->id,
            'rnc_emisor' => '132036352',
            'numero_ecf' => 'B010000000001',
            'status' => 'pending',
        ]);

        \App\Models\EcfReceived::create([
            'business_id' => $businessB->id,
            'rnc_emisor' => '131880681',
            'numero_ecf' => 'B010000000001',
            'status' => 'pending',
        ]);

        $userA = User::factory()->create(['business_id' => $businessA->id, 'role' => 'business_admin']);
        $this->actingAs($userA);

        $received = \App\Models\EcfReceived::all();
        $this->assertEquals(1, $received->count());
        $this->assertEquals($businessA->id, $received->first()->business_id);
    }

    public function test_emit_throws_when_ecf_belongs_to_different_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $configA = BusinessFeConfig::factory()->create(['business_id' => $businessA->id]);
        $configA->forceFill([
            'password_certificado' => Crypt::encryptString('pass'),
            'activo' => true,
        ])->save();

        $ecfFromB = Ecf::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(\InvalidArgumentException::class);

        $service = new ElectronicInvoiceService($businessA, $configA);
        $service->emit($ecfFromB, []);
    }

    public function test_ncf_rangos_isolated_per_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $rangoA = NcfRango::factory()->create([
            'business_id' => $businessA->id,
            'tipo_ecf' => 32,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 10,
        ]);

        $rangoB = NcfRango::factory()->create([
            'business_id' => $businessB->id,
            'tipo_ecf' => 32,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 50,
        ]);

        $encfA = $rangoA->assignNextSecuencial();
        $encfB = $rangoB->assignNextSecuencial();

        $this->assertEquals('E320000000010', $encfA);
        $this->assertEquals('E320000000050', $encfB);

        // Ensure they didn't interfere
        $this->assertDatabaseHas('ncf_rangos', ['id' => $rangoA->id, 'proximo_secuencial' => 11]);
        $this->assertDatabaseHas('ncf_rangos', ['id' => $rangoB->id, 'proximo_secuencial' => 51]);
    }

    public function test_fe_config_isolation(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        BusinessFeConfig::factory()->create(['business_id' => $businessA->id, 'rnc_emisor' => '111111111']);
        BusinessFeConfig::factory()->create(['business_id' => $businessB->id, 'rnc_emisor' => '222222222']);

        $userA = User::factory()->create(['business_id' => $businessA->id, 'role' => 'business_admin']);
        $this->actingAs($userA);

        $configs = BusinessFeConfig::all();
        $this->assertEquals(1, $configs->count());
        $this->assertEquals('111111111', $configs->first()->rnc_emisor);
    }

    public function test_audit_logs_isolated_per_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        \App\Models\EcfAuditLog::create([
            'business_id' => $businessA->id,
            'action' => 'test_action',
        ]);

        \App\Models\EcfAuditLog::create([
            'business_id' => $businessB->id,
            'action' => 'test_action',
        ]);

        $userA = User::factory()->create(['business_id' => $businessA->id, 'role' => 'business_admin']);
        $this->actingAs($userA);

        $logs = \App\Models\EcfAuditLog::all();
        $this->assertEquals(1, $logs->count());
        $this->assertEquals($businessA->id, $logs->first()->business_id);
    }
}
