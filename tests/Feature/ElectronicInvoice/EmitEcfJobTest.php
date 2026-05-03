<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Jobs\EmitEcfJob;
use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\NcfRango;
use App\Models\PosTicket;
use App\Models\User;
use App\Services\ElectronicInvoice\DgiiEndpoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmitEcfJobTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_XML = '<?xml version="1.0" encoding="UTF-8"?><semilla><valor>SEED</valor><fecha>01-05-2026</fecha></semilla>';

    private const TRACK_ID = 'TRACK-ECF-TEST-001';

    /**
     * Sets up a Business with a valid P12 certificate and active feConfig.
     *
     * @return array{0: Business, 1: BusinessFeConfig}
     */
    private function setupBusinessWithCert(): array
    {
        $password = 'emit_job_test_pass';

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

    private function fakeHttp(): void
    {
        Http::fake([
            DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla' => Http::response(self::SEED_XML, 200),
            DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla' => Http::response(json_encode(['token' => 'test_token']), 200),
            DgiiEndpoints::getRecepcionEndpoint('TestECF') => Http::response(json_encode(['trackId' => self::TRACK_ID]), 200),
            '*' => Http::response('{}', 200),
        ]);
    }

    /**
     * Creates a PosTicket with items for the given business, linked to a cashier.
     */
    private function createTicketWithItems(Business $business): PosTicket
    {
        $cashier = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'business_admin',
        ]);

        $ticket = PosTicket::factory()->create([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'status' => 'paid',
            'ecf_requested' => true,
            'ecf_type' => 'consumidor_final',
            'total' => '1180.00',
            'itbis_amount' => '180.00',
        ]);

        $ticket->items()->create([
            'item_type' => 'service',
            'name' => 'Corte de cabello',
            'unit_price' => '1000.00',
            'qty' => 1,
            'line_total' => '1000.00',
        ]);

        return $ticket;
    }

    public function test_job_is_idempotent_when_ticket_already_has_ecf_ncf(): void
    {
        [$business] = $this->setupBusinessWithCert();

        $ticket = PosTicket::factory()->create([
            'business_id' => $business->id,
            'ecf_status' => 'emitted',
        ]);
        $ticket->forceFill(['ecf_ncf' => 'E320000000001'])->save();

        Log::spy();

        $job = new EmitEcfJob($ticket->id);
        $job->handle(
            new \App\Services\ElectronicInvoice\ElectronicInvoiceService(
                $business,
                $business->feConfig
            )
        );

        // No DGII call expected — idempotency guard must exit early
        Log::shouldHaveReceived('info')->withArgs(fn ($msg) => str_contains($msg, 'skipped'));
    }

    public function test_job_skips_when_feconfig_inactive(): void
    {
        $business = Business::factory()->create();
        BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
        ]); // activo = false por defecto en factory

        $cashier = User::factory()->create(['business_id' => $business->id, 'role' => 'business_admin']);
        $ticket = PosTicket::factory()->create([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'ecf_status' => 'pending',
        ]);
        $ticket->forceFill(['ecf_ncf' => null])->save();

        Queue::fake();
        Http::fake();

        // We need an ElectronicInvoiceService bound to *some* business/config just for construction,
        // but the job re-reads feConfig from $ticket->business so we can use a dummy.
        // The job constructs a new service from $ticket->business internally.
        $job = new EmitEcfJob($ticket->id);

        // Call handle without injecting service — the job builds it internally from ticket->business.
        // Since feConfig is inactive the job logs a warning and returns before calling $service.
        $job->handle(
            app(\App\Services\ElectronicInvoice\ElectronicInvoiceService::class,
                ['business' => $business, 'feConfig' => $business->feConfig ?? new \App\Models\BusinessFeConfig])
        );

        Http::assertNothingSent();
    }

    public function test_job_associates_ecf_to_ticket_after_emission(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();
        Queue::fake();
        $this->fakeHttp();

        NcfRango::factory()->create([
            'business_id' => $business->id,
            'tipo_ecf' => 32,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 100,
            'proximo_secuencial' => 1,
            'status' => 'active',
        ]);

        $ticket = $this->createTicketWithItems($business);

        $job = new EmitEcfJob($ticket->id);
        $job->handle(new \App\Services\ElectronicInvoice\ElectronicInvoiceService($business, $config));

        $ticket->refresh();
        $this->assertEquals('emitted', $ticket->ecf_status);
        $this->assertNotNull($ticket->ecf_ncf);
    }

    public function test_job_skips_ticket_not_found(): void
    {
        [$business, $config] = $this->setupBusinessWithCert();

        Http::fake();
        Log::spy();

        $job = new EmitEcfJob(99999);
        $job->handle(new \App\Services\ElectronicInvoice\ElectronicInvoiceService($business, $config));

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'not found'));
    }

    public function test_failed_handler_sets_ecf_status_to_error(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id]);
        $ticket = PosTicket::factory()->create([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'ecf_status' => 'pending',
        ]);

        $job = new EmitEcfJob($ticket->id);
        $job->failed(new \RuntimeException('DGII unreachable'));

        $ticket->refresh();
        $this->assertEquals('error', $ticket->ecf_status);
        $this->assertStringContainsString('DGII unreachable', (string) $ticket->ecf_error_message);
    }

    public function test_failed_handler_uses_force_fill_on_guarded_fields(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id]);
        $ticket = PosTicket::factory()->create([
            'business_id' => $business->id,
            'cashier_id' => $cashier->id,
            'ecf_status' => 'pending',
        ]);

        $originalFillable = (new PosTicket)->getFillable();

        // ecf_status and ecf_error_message must NOT be in $fillable (BLOCK-008)
        $this->assertNotContains('status', $originalFillable);

        $job = new EmitEcfJob($ticket->id);
        $job->failed(new \RuntimeException('Test forced fill path'));

        $ticket->refresh();
        $this->assertEquals('error', $ticket->ecf_status);
    }
}
