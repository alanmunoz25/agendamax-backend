<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Services\ElectronicInvoice\DgiiAuthService;
use App\Services\ElectronicInvoice\DgiiEndpoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DgiiAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_XML = '<?xml version="1.0" encoding="UTF-8"?><semilla><valor>TEST_SEED_VALUE_12345</valor><fecha>01-05-2026</fecha></semilla>';

    private const TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test_token';

    /**
     * Creates a self-signed P12 in memory and returns [p12Bytes, password].
     */
    private function createTestP12(): array
    {
        $password = 'test_password_123';

        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $dn = ['C' => 'DO', 'O' => 'Test SRL', 'CN' => 'test.do'];
        $csr = openssl_csr_new($dn, $privKey, []);
        $cert = openssl_csr_sign($csr, null, $privKey, 365, []);

        openssl_pkcs12_export($cert, $p12Bytes, $privKey, $password);

        return [$p12Bytes, $password];
    }

    /**
     * Creates a Business + BusinessFeConfig with a real P12 stored as base64 in the DB.
     * Returns [$business, $config].
     */
    private function makeServicesWithCert(): array
    {
        [$p12Bytes, $password] = $this->createTestP12();

        $business = Business::factory()->create();

        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
            'certificado_convertido' => true,
        ]);
        // Guarded fields: set via direct property / forceFill (service-layer pattern)
        $config->certificado_digital = base64_encode($p12Bytes);
        $config->forceFill([
            'password_certificado' => Crypt::encryptString($password),
            'ambiente' => 'TestECF',
            'activo' => true,
        ])->save();

        return [$business, $config];
    }

    public function test_get_token_calls_dgii_and_returns_token(): void
    {
        [$business, $config] = $this->makeServicesWithCert();

        $seedUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla';
        $validateUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla';

        Http::fake([
            $seedUrl => Http::response(self::SEED_XML, 200),
            $validateUrl => Http::response(json_encode(['token' => self::TOKEN]), 200),
        ]);

        $service = new DgiiAuthService($business, $config);
        $token = $service->getToken();

        $this->assertEquals(self::TOKEN, $token);
    }

    public function test_token_is_cached_after_first_call(): void
    {
        [$business, $config] = $this->makeServicesWithCert();

        $seedUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla';
        $validateUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla';

        Http::fake([
            $seedUrl => Http::response(self::SEED_XML, 200),
            $validateUrl => Http::response(json_encode(['token' => self::TOKEN]), 200),
        ]);

        $service = new DgiiAuthService($business, $config);
        $first = $service->getToken();
        $second = $service->getToken();

        $this->assertEquals($first, $second);

        // Seed should only be called once (second call hits cache)
        Http::assertSentCount(2); // one for seed + one for validate
    }

    public function test_cached_token_returned_on_second_call(): void
    {
        [$business, $config] = $this->makeServicesWithCert();

        $cacheKey = config('electronic-invoice.token_cache_prefix').$business->id;
        Cache::put($cacheKey, self::TOKEN.'_cached', 3600);

        Http::fake(); // Should not call HTTP at all

        $service = new DgiiAuthService($business, $config);
        $token = $service->getToken();

        $this->assertEquals(self::TOKEN.'_cached', $token);
        Http::assertNothingSent();
    }

    public function test_clear_token_cache_invalidates_token(): void
    {
        [$business, $config] = $this->makeServicesWithCert();

        $cacheKey = config('electronic-invoice.token_cache_prefix').$business->id;
        Cache::put($cacheKey, self::TOKEN, 3600);

        $seedUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla';
        $validateUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla';

        Http::fake([
            $seedUrl => Http::response(self::SEED_XML, 200),
            $validateUrl => Http::response(json_encode(['token' => 'NEW_TOKEN']), 200),
        ]);

        $service = new DgiiAuthService($business, $config);
        $service->clearTokenCache();

        $token = $service->getToken();

        $this->assertEquals('NEW_TOKEN', $token);
    }

    public function test_throws_when_dgii_seed_fails(): void
    {
        [$business, $config] = $this->makeServicesWithCert();

        $seedUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla';
        Http::fake([$seedUrl => Http::response('Server Error', 500)]);

        $this->expectException(\RuntimeException::class);

        $service = new DgiiAuthService($business, $config);
        $service->getToken();
    }

    public function test_throws_when_token_not_in_validate_response(): void
    {
        [$business, $config] = $this->makeServicesWithCert();

        $seedUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/Semilla';
        $validateUrl = DgiiEndpoints::getAutenticacionBaseUrl('TestECF').'/ValidarSemilla';

        Http::fake([
            $seedUrl => Http::response(self::SEED_XML, 200),
            $validateUrl => Http::response(json_encode(['error' => 'invalid']), 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/token/i');

        $service = new DgiiAuthService($business, $config);
        $service->getToken();
    }
}
