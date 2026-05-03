<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Jobs\PollEcfStatus;
use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Services\ElectronicInvoice\DgiiEndpoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PollEcfStatusJobTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_XML = '<?xml version="1.0" encoding="UTF-8"?><semilla><valor>SEED</valor><fecha>01-05-2026</fecha></semilla>';

    /**
     * Creates a Business + BusinessFeConfig with an in-memory P12 stored as base64 in the DB.
     */
    private function setupBusinessWithCert(): array
    {
        $password = 'poll_test_pass';

        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $dn = ['C' => 'DO', 'O' => 'Test SRL', 'CN' => 'test.do'];
        $csr = openssl_csr_new($dn, $privKey, []);
        $cert = openssl_csr_sign($csr, null, $privKey, 365, []);

        openssl_pkcs12_export($cert, $p12Bytes, $privKey, $password);

        $business = Business::factory()->create();

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

        return [$business, $config];
    }

    private function fakeTokenAndStatus(string $statusResponse): void
    {
        Http::fake([
            DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla' => Http::response(self::SEED_XML, 200),
            DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla' => Http::response(json_encode(['token' => 'test_token']), 200),
            DgiiEndpoints::getConsultaEstadoEndpoint('TestECF').'*' => Http::response($statusResponse, 200),
            '*' => Http::response('{}', 200),
        ]);
    }

    public function test_job_updates_status_to_accepted(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->sent()->create([
            'business_id' => $business->id,
            'track_id' => 'TRACK-001',
        ]);

        $this->fakeTokenAndStatus(json_encode(['estado' => 'Aceptado', 'mensaje' => 'OK']));

        $job = new PollEcfStatus($ecf->id);
        $job->handle();

        $ecf->refresh();
        $this->assertEquals('accepted', $ecf->status);
    }

    public function test_job_updates_status_to_rejected(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->sent()->create([
            'business_id' => $business->id,
            'track_id' => 'TRACK-002',
        ]);

        $this->fakeTokenAndStatus(json_encode(['estado' => 'Rechazado', 'mensaje' => 'Error en datos']));

        $job = new PollEcfStatus($ecf->id);
        $job->handle();

        $ecf->refresh();
        $this->assertEquals('rejected', $ecf->status);
        $this->assertStringContainsString('Error en datos', $ecf->error_message ?? '');
    }

    public function test_job_skips_terminal_status(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->accepted()->create(['business_id' => $business->id]);

        Http::fake(); // Should not call HTTP at all

        $job = new PollEcfStatus($ecf->id);
        $job->handle();

        Http::assertNothingSent();
        $ecf->refresh();
        $this->assertEquals('accepted', $ecf->status);
    }

    public function test_job_skips_when_ecf_not_found(): void
    {
        $job = new PollEcfStatus(99999);
        $job->handle();

        $this->assertTrue(true); // No exception = pass
    }

    public function test_job_writes_audit_log_on_resolution(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        $ecf = Ecf::factory()->sent()->create([
            'business_id' => $business->id,
            'track_id' => 'TRACK-AUDIT-001',
        ]);

        $this->fakeTokenAndStatus(json_encode(['estado' => 'Aceptado', 'mensaje' => 'OK']));

        $job = new PollEcfStatus($ecf->id);
        $job->handle();

        $this->assertDatabaseHas('ecf_audit_logs', [
            'business_id' => $business->id,
            'ecf_id' => $ecf->id,
            'action' => 'poll_status_resolved',
        ]);
    }
}
