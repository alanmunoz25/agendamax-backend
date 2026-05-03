<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterManualVoidTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private BusinessFeConfig $feConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $this->feConfig = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $this->feConfig->forceFill(['activo' => true, 'ambiente' => 'TestECF'])->save();
    }

    private function validPayload(): array
    {
        return [
            'manual_void_ncf' => 'E34'.str_pad('1', 10, '0', STR_PAD_LEFT),
            'reason' => 'Anulación por solicitud del cliente, excede el plazo permitido.',
        ];
    }

    public function test_register_manual_void_updates_ecf_status_to_voided_manual(): void
    {
        $ecf = Ecf::factory()->accepted()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.register-manual-void', $ecf), $this->validPayload());

        $response->assertRedirect(route('electronic-invoice.issued.show', $ecf));
        $response->assertSessionHas('success');

        $ecf->refresh();
        $this->assertEquals('voided_manual', $ecf->status);
        $this->assertEquals('E34'.str_pad('1', 10, '0', STR_PAD_LEFT), $ecf->manual_void_ncf);
        $this->assertNotNull($ecf->voided_at);
        $this->assertEquals($this->admin->id, $ecf->voided_by);
    }

    public function test_register_manual_void_creates_audit_log_entry(): void
    {
        $ecf = Ecf::factory()->accepted()->create([
            'business_id' => $this->business->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.register-manual-void', $ecf), $this->validPayload());

        $this->assertDatabaseHas('ecf_audit_logs', [
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'action' => 'manual_void_registered',
        ]);
    }

    public function test_register_manual_void_validates_ncf_format(): void
    {
        $ecf = Ecf::factory()->accepted()->create([
            'business_id' => $this->business->id,
        ]);

        $invalidPayloads = [
            ['manual_void_ncf' => 'E310000000001', 'reason' => 'Razon valida para la prueba.'], // wrong type (31 not 34)
            ['manual_void_ncf' => 'E3400000001', 'reason' => 'Razon valida para la prueba.'],   // too short
            ['manual_void_ncf' => '0000000000034', 'reason' => 'Razon valida para la prueba.'], // no prefix
            ['manual_void_ncf' => '', 'reason' => 'Razon valida para la prueba.'],               // empty
        ];

        foreach ($invalidPayloads as $payload) {
            $response = $this->actingAs($this->admin)
                ->post(route('electronic-invoice.issued.register-manual-void', $ecf), $payload);

            $response->assertSessionHasErrors('manual_void_ncf');
        }
    }

    public function test_employee_receives_403_when_attempting_manual_void(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $ecf = Ecf::factory()->accepted()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->actingAs($employee)
            ->post(route('electronic-invoice.issued.register-manual-void', $ecf), $this->validPayload());

        $response->assertForbidden();
    }

    public function test_register_manual_void_enforces_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherEcf = Ecf::factory()->accepted()->create([
            'business_id' => $otherBusiness->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.register-manual-void', $otherEcf), $this->validPayload());

        // BelongsToBusiness global scope causes 404 before controller logic
        $response->assertNotFound();

        $otherEcf->refresh();
        $this->assertNotEquals('voided_manual', $otherEcf->status);
    }
}
