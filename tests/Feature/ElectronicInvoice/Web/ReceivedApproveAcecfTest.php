<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\EcfReceived;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceivedApproveAcecfTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private BusinessFeConfig $feConfig;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $this->feConfig = $this->createActiveFeConfig($this->business);
    }

    /**
     * Creates a BusinessFeConfig with a real in-memory P12 certificate so
     * XmlSignerService can sign the ACECF without hitting a real DGII endpoint.
     */
    private function createActiveFeConfig(Business $business): BusinessFeConfig
    {
        $password = 'approve_test_pass';

        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $dn = ['C' => 'DO', 'O' => 'Test SRL', 'CN' => 'test.do'];
        $csr = openssl_csr_new($dn, $privKey, []);
        $cert = openssl_csr_sign($csr, null, $privKey, 365, []);
        openssl_pkcs12_export($cert, $p12Bytes, $privKey, $password);

        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
            'certificado_convertido' => true,
        ]);
        $config->certificado_digital = base64_encode($p12Bytes);
        $config->forceFill([
            'password_certificado' => Crypt::encryptString($password),
            'ambiente' => 'TestECF',
            'activo' => true,
        ])->save();

        return $config;
    }

    public function test_approve_updates_status_to_accepted(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'pending',
            'numero_ecf' => 'E310000000001',
            'rnc_emisor' => '101012345',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $received));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ecf_received', [
            'id' => $received->id,
            'status' => 'accepted',
        ]);
    }

    public function test_approve_generates_and_persists_acecf_xml(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'pending',
            'numero_ecf' => 'E310000000002',
            'rnc_emisor' => '101012345',
        ]);

        $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $received));

        $received->refresh();

        // The service must have written the ACECF XML to storage
        $this->assertNotNull($received->xml_arecf_path);
        Storage::assertExists($received->xml_arecf_path);
    }

    public function test_approve_fails_when_not_pending(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $received));

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('ecf_received', [
            'id' => $received->id,
            'status' => 'accepted', // unchanged
        ]);
    }

    public function test_approve_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherReceived = EcfReceived::factory()->create([
            'business_id' => $otherBusiness->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $otherReceived));

        // BelongsToBusiness global scope causes 404 before controller logic
        $response->assertNotFound();

        $this->assertDatabaseHas('ecf_received', [
            'id' => $otherReceived->id,
            'status' => 'pending', // not changed
        ]);
    }
}
