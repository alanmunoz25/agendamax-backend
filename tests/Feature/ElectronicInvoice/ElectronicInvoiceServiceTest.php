<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Jobs\PollEcfStatus;
use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Services\ElectronicInvoice\DgiiEndpoints;
use App\Services\ElectronicInvoice\ElectronicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ElectronicInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_XML = '<?xml version="1.0" encoding="UTF-8"?><semilla><valor>SEED</valor><fecha>01-05-2026</fecha></semilla>';

    private const TOKEN = 'test_bearer_token_abc';

    private const TRACK_ID = 'TRACK-UUID-12345';

    /**
     * Creates a Business + BusinessFeConfig with an in-memory P12 stored as base64 in the DB.
     */
    private function setupBusinessWithCert(): array
    {
        $password = 'cert_pass_123';

        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $dn = ['C' => 'DO', 'O' => 'Test SRL', 'CN' => 'test.do'];
        $csr = openssl_csr_new($dn, $privKey, []);
        $cert = openssl_csr_sign($csr, null, $privKey, 365, []);

        openssl_pkcs12_export($cert, $p12Bytes, $privKey, $password);

        $business = Business::factory()->create();

        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
            'razon_social' => 'SALON PRUEBA SRL',
            'certificado_convertido' => true,
        ]);
        $config->certificado_digital = base64_encode($p12Bytes);
        $config->forceFill([
            'password_certificado' => Crypt::encryptString($password),
            'ambiente' => 'TestECF',
            'activo' => true,
        ])->save();

        return [$business, $config];
    }

    private function fakeHttp(): void
    {
        $seedUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla';
        $validateUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla';
        $recepcionUrl = DgiiEndpoints::getRecepcionEndpoint('TestECF');

        Http::fake([
            $seedUrl => Http::response(self::SEED_XML, 200),
            $validateUrl => Http::response(json_encode(['token' => self::TOKEN]), 200),
            $recepcionUrl => Http::response(json_encode(['trackId' => self::TRACK_ID]), 200),
            '*' => Http::response('{}', 200),
        ]);
    }

    public function test_emit_happy_path_sets_status_sent_and_track_id(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->create([
            'business_id' => $business->id,
            'numero_ecf' => 'B020000000001',
            'tipo' => '32',
            'monto_gravado' => '2000.00',
            'itbis_total' => '360.00',
            'monto_total' => '2360.00',
        ]);

        Queue::fake();
        $this->fakeHttp();

        $service = new ElectronicInvoiceService($business, $config);
        $service->emit($ecf, [
            ['name' => 'Servicio 1', 'quantity' => 1.0, 'unit_price' => 2000.0],
        ]);

        $ecf->refresh();

        $this->assertEquals('sent', $ecf->status);
        $this->assertEquals(self::TRACK_ID, $ecf->track_id);

        Queue::assertPushed(PollEcfStatus::class, function (PollEcfStatus $job) use ($ecf) {
            return $job->ecfId === $ecf->id;
        });
    }

    public function test_emit_throws_on_business_id_mismatch(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();
        $otherBusiness = Business::factory()->create();

        $ecf = Ecf::factory()->create(['business_id' => $otherBusiness->id]);

        $this->expectException(\InvalidArgumentException::class);

        $service = new ElectronicInvoiceService($business, $config);
        $service->emit($ecf, []);
    }

    public function test_emit_persists_xml_to_storage(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->create([
            'business_id' => $business->id,
            'numero_ecf' => 'B020000000002',
            'tipo' => '32',
            'monto_gravado' => '500.00',
            'itbis_total' => '90.00',
            'monto_total' => '590.00',
        ]);

        Queue::fake();
        $this->fakeHttp();

        $service = new ElectronicInvoiceService($business, $config);
        $service->emit($ecf, [
            ['name' => 'Test Service', 'quantity' => 1.0, 'unit_price' => 500.0],
        ]);

        $ecf->refresh();
        $this->assertNotNull($ecf->xml_path);
        $this->assertStringContainsString('ecf_enviados', $ecf->xml_path);
    }

    public function test_emit_writes_audit_log(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->create([
            'business_id' => $business->id,
            'numero_ecf' => 'B020000000003',
            'tipo' => '32',
            'monto_gravado' => '500.00',
            'itbis_total' => '90.00',
            'monto_total' => '590.00',
        ]);

        Queue::fake();
        $this->fakeHttp();

        $service = new ElectronicInvoiceService($business, $config);
        $service->emit($ecf, [
            ['name' => 'Test', 'quantity' => 1.0, 'unit_price' => 500.0],
        ]);

        $this->assertDatabaseHas('ecf_audit_logs', [
            'business_id' => $business->id,
            'action' => 'send_ecf',
        ]);
    }
}
