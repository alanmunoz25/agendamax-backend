<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Services\ElectronicInvoice\XmlSignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class XmlSignerServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_XML = '<?xml version="1.0" encoding="UTF-8"?><ECF><Encabezado><Version>1.0</Version></Encabezado><FechaHoraFirma>01-05-2026 10:00:00</FechaHoraFirma></ECF>';

    /**
     * Generates a self-signed P12 certificate in memory for testing.
     * Returns [p12Bytes, password].
     */
    private function createTestP12(): array
    {
        $password = 'test_password_123';

        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $dn = [
            'C' => 'DO',
            'ST' => 'Distrito Nacional',
            'O' => 'Test Business SRL',
            'CN' => 'test.business.do',
        ];

        $csr = openssl_csr_new($dn, $privKey, ['private_key_bits' => 2048]);
        $cert = openssl_csr_sign($csr, null, $privKey, 365, []);

        openssl_pkcs12_export($cert, $p12Bytes, $privKey, $password);

        return [$p12Bytes, $password];
    }

    /**
     * Creates a BusinessFeConfig with a real in-memory P12 stored as base64 in the DB.
     */
    private function makeConfigWithCert(Business $business): array
    {
        [$p12Bytes, $password] = $this->createTestP12();

        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'certificado_convertido' => true,
        ]);
        $config->certificado_digital = base64_encode($p12Bytes);
        $config->forceFill([
            'password_certificado' => Crypt::encryptString($password),
            'activo' => true,
        ])->save();

        return [$config, $password];
    }

    public function test_sign_returns_xml_with_signature_node(): void
    {
        $business = Business::factory()->create();
        [$config] = $this->makeConfigWithCert($business);

        $service = new XmlSignerService($business, $config);
        $signedXml = $service->sign(self::SAMPLE_XML);

        $this->assertNotEmpty($signedXml);
        $this->assertTrue($service->isSigned($signedXml), 'Signed XML must contain a Signature node');

        $dom = simplexml_load_string($signedXml);
        $this->assertNotFalse($dom, 'Signed XML must be valid XML');
    }

    public function test_extract_codigo_seguridad_returns_6_chars(): void
    {
        $business = Business::factory()->create();
        [$config] = $this->makeConfigWithCert($business);

        $service = new XmlSignerService($business, $config);
        $signedXml = $service->sign(self::SAMPLE_XML);
        $codigo = $service->extractCodigoSeguridad($signedXml);

        $this->assertNotNull($codigo);
        $this->assertEquals(6, strlen($codigo));
    }

    public function test_is_signed_returns_false_for_unsigned_xml(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create(['business_id' => $business->id]);

        $service = new XmlSignerService($business, $config);

        $this->assertFalse($service->isSigned(self::SAMPLE_XML));
    }

    public function test_throws_when_cert_not_found(): void
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'certificado_convertido' => false,
        ]);
        $config->forceFill([
            'password_certificado' => Crypt::encryptString('some_password'),
        ])->save();

        $service = new XmlSignerService($business, $config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/certificado no encontrado/i');

        $service->sign(self::SAMPLE_XML);
    }

    public function test_throws_when_no_cert_password(): void
    {
        [$p12Bytes] = $this->createTestP12();

        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'certificado_convertido' => true,
        ]);
        // Set cert but explicitly leave password_certificado as null
        $config->certificado_digital = base64_encode($p12Bytes);
        $config->save();

        $service = new XmlSignerService($business, $config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/contraseña/i');

        $service->sign(self::SAMPLE_XML);
    }
}
