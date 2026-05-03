<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class BusinessFeConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_business_fe_config(): void
    {
        $business = Business::factory()->create();

        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
            'razon_social' => 'EMPRESA DEMO SRL',
        ]);

        $this->assertDatabaseHas('business_fe_configs', [
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
        ]);

        $this->assertEquals($business->id, $config->business_id);
    }

    public function test_unique_constraint_one_config_per_business(): void
    {
        $business = Business::factory()->create();
        BusinessFeConfig::factory()->create(['business_id' => $business->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BusinessFeConfig::factory()->create(['business_id' => $business->id]);
    }

    public function test_cert_password_is_encrypted_and_decrypted(): void
    {
        $business = Business::factory()->create();
        $plainPassword = 'my_secret_password_123';

        $config = BusinessFeConfig::factory()->create(['business_id' => $business->id]);
        // password_certificado is guarded — use forceFill from trusted service layer
        $config->forceFill(['password_certificado' => Crypt::encryptString($plainPassword)])->save();

        $decrypted = $config->getDecryptedCertPassword();

        $this->assertEquals($plainPassword, $decrypted);
        $this->assertNotEquals($plainPassword, $config->getAttributes()['password_certificado']);
    }

    public function test_has_certificate_returns_false_when_no_cert(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create(['business_id' => $business->id]);

        $this->assertFalse($config->hasCertificate());
    }

    public function test_has_certificate_returns_false_when_not_converted(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'certificado_convertido' => false,
        ]);
        // certificado_digital is guarded — assign via direct property (encrypted cast applies)
        $config->certificado_digital = base64_encode('fake_p12_bytes');
        $config->save();

        $this->assertFalse($config->hasCertificate());
    }

    public function test_has_certificate_returns_true_when_converted(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'certificado_convertido' => true,
        ]);
        $config->certificado_digital = base64_encode('fake_p12_bytes');
        $config->save();

        $this->assertTrue($config->hasCertificate());
    }

    public function test_get_certificate_p12_returns_decoded_bytes(): void
    {
        $business = Business::factory()->create();
        $fakeBytes = 'binary_p12_content_here';

        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'certificado_convertido' => true,
        ]);
        $config->certificado_digital = base64_encode($fakeBytes);
        $config->save();

        $this->assertEquals($fakeBytes, $config->getCertificateP12());
    }

    public function test_get_certificate_p12_throws_when_no_certificate(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create(['business_id' => $business->id]);

        $this->expectException(\RuntimeException::class);

        $config->getCertificateP12();
    }

    public function test_is_ready_to_emit_returns_false_when_inactive(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create(['business_id' => $business->id]);
        // activo is guarded — defaults to false from configure() afterCreating

        $this->assertFalse($config->isReadyToEmit());
    }

    public function test_is_ready_to_emit_returns_true_with_certificate(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
            'razon_social' => 'EMPRESA DEMO SRL',
            'certificado_convertido' => true,
        ]);
        // Guarded fields must be set via forceFill / direct property assignment
        $config->certificado_digital = base64_encode('fake_p12_bytes');
        $config->forceFill([
            'activo' => true,
            'password_certificado' => Crypt::encryptString('secret'),
        ])->save();

        $this->assertTrue($config->isReadyToEmit());
    }

    public function test_multi_tenant_isolation_config_belongs_to_its_business(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $configA = BusinessFeConfig::factory()->create(['business_id' => $businessA->id]);
        $configB = BusinessFeConfig::factory()->create(['business_id' => $businessB->id]);

        // Scope as businessA admin
        $userA = User::factory()->create(['business_id' => $businessA->id, 'role' => 'business_admin']);
        $this->actingAs($userA);

        $visible = BusinessFeConfig::all();
        $ids = $visible->pluck('id');

        $this->assertTrue($ids->contains($configA->id));
        $this->assertFalse($ids->contains($configB->id));
    }

    public function test_cascade_delete_removes_config_when_business_is_deleted(): void
    {
        $business = Business::factory()->create();
        BusinessFeConfig::factory()->create(['business_id' => $business->id]);

        $business->delete();

        $this->assertDatabaseMissing('business_fe_configs', ['business_id' => $business->id]);
    }
}
