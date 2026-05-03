<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\EcfAuditLog;
use DOMDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Selective\XmlDSig\Algorithm;
use Selective\XmlDSig\CryptoSigner;
use Selective\XmlDSig\PrivateKeyStore;
use Selective\XmlDSig\XmlSigner;

class DgiiAuthService
{
    private readonly string $ambiente;

    private readonly int $timeout;

    public function __construct(
        private readonly Business $business,
        private readonly BusinessFeConfig $feConfig
    ) {
        $this->ambiente = $feConfig->ambiente;
        $this->timeout = config('electronic-invoice.dgii_timeout', 30);
    }

    /**
     * Returns a valid DGII token for this business.
     * Token is cached per-business to avoid repeated authentication round-trips.
     */
    public function getToken(): string
    {
        $cacheKey = config('electronic-invoice.token_cache_prefix').$this->business->id;

        return Cache::remember($cacheKey, config('electronic-invoice.token_cache_ttl', 3600), function () {
            Log::info('[DgiiAuth] Cache miss — generating new token', [
                'business_id' => $this->business->id,
                'ambiente' => $this->ambiente,
            ]);

            return $this->authenticate();
        });
    }

    /**
     * Invalidates the cached token for this business.
     */
    public function clearTokenCache(): void
    {
        $cacheKey = config('electronic-invoice.token_cache_prefix').$this->business->id;
        Cache::forget($cacheKey);
    }

    /**
     * Full DGII authentication flow: get seed → sign → validate → return token.
     *
     * @throws \RuntimeException on any authentication failure
     */
    private function authenticate(): string
    {
        $start = hrtime(true);

        // Step 1: Get seed
        Log::info('[DgiiAuth] Step 1: Getting seed', ['business_id' => $this->business->id]);
        [$seedXml] = $this->getSeed();

        // Step 2: Sign seed XML with business certificate
        Log::info('[DgiiAuth] Step 2: Signing seed XML', ['business_id' => $this->business->id]);
        $signedXml = $this->signXml($seedXml);

        // Step 3: Validate signed seed with DGII → receive token
        Log::info('[DgiiAuth] Step 3: Validating signed seed', ['business_id' => $this->business->id]);
        $token = $this->validateSignedSeed($signedXml);

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        $this->audit('authenticate', null, ['ambiente' => $this->ambiente], ['token_prefix' => substr($token, 0, 8).'...'], 200, null, $durationMs);

        Log::info('[DgiiAuth] Authentication successful', [
            'business_id' => $this->business->id,
            'duration_ms' => $durationMs,
        ]);

        return $token;
    }

    /**
     * Fetches the DGII authentication seed.
     *
     * @return array{0: string, 1: string, 2: string} [rawXml, valor, fecha]
     *
     * @throws \RuntimeException
     */
    private function getSeed(): array
    {
        $url = DgiiEndpoints::getAutenticacionBaseUrl($this->ambiente).'/Semilla';

        $start = hrtime(true);

        Log::info('[DgiiAuth] GET seed', ['url' => $url]);

        try {
            $response = Http::timeout($this->timeout)
                ->accept('application/json')
                ->get($url);
        } catch (\Throwable $e) {
            $this->audit('get_seed', null, ['url' => $url], null, 0, $e->getMessage());
            throw new \RuntimeException('Error conectando a DGII (semilla): '.$e->getMessage(), 0, $e);
        }

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        if (! $response->successful()) {
            $this->audit('get_seed', null, ['url' => $url], ['body' => $response->body()], $response->status(), 'HTTP '.$response->status(), $durationMs);
            throw new \RuntimeException('DGII seed HTTP '.$response->status().': '.$response->body());
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            $this->audit('get_seed', null, ['url' => $url], ['body' => $response->body()], $response->status(), 'XML parse error', $durationMs);
            throw new \RuntimeException('DGII seed: no se pudo parsear XML de respuesta.');
        }

        $valor = (string) ($xml->valor ?? '');
        $fecha = (string) ($xml->fecha ?? '');

        $this->audit('get_seed', null, ['url' => $url], ['valor_prefix' => substr($valor, 0, 20), 'fecha' => $fecha], $response->status(), null, $durationMs);

        Log::info('[DgiiAuth] Seed obtained', ['valor_prefix' => substr($valor, 0, 20), 'fecha' => $fecha]);

        return [$response->body(), $valor, $fecha];
    }

    /**
     * Signs the seed XML using the business P12 certificate stored in the database.
     *
     * @throws \RuntimeException
     */
    private function signXml(string $xmlContent): string
    {
        if (! $this->feConfig->hasCertificate()) {
            throw new \RuntimeException("Certificado no encontrado para business {$this->business->id}: no hay certificado digital convertido.");
        }

        $certPassword = $this->feConfig->getDecryptedCertPassword();

        if ($certPassword === null) {
            throw new \RuntimeException("No hay contraseña de certificado configurada para business {$this->business->id}.");
        }

        $certData = $this->feConfig->getCertificateP12();
        $certs = [];

        if (! openssl_pkcs12_read($certData, $certs, $certPassword)) {
            $error = openssl_error_string() ?: 'Contraseña incorrecta o certificado corrupto';
            throw new \RuntimeException("Error leyendo PKCS12 (business {$this->business->id}): {$error}");
        }

        $pemContents = $certs['cert'].$certs['pkey'];

        $dom = new DOMDocument;
        $dom->loadXML($xmlContent, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $cleanedXml = $dom->C14N(true, false);

        $privateKeyStore = new PrivateKeyStore;
        $privateKeyStore->loadFromPem($pemContents, $certPassword);
        $privateKeyStore->addCertificatesFromX509Pem($pemContents);

        $algorithm = new Algorithm(Algorithm::METHOD_SHA256);
        $cryptoSigner = new CryptoSigner($privateKeyStore, $algorithm);
        $xmlSigner = new XmlSigner($cryptoSigner);
        $xmlSigner->setReferenceUri('');

        $signedXml = $xmlSigner->signXml($cleanedXml);

        Log::info('[DgiiAuth] Seed signed successfully', ['business_id' => $this->business->id]);

        return $signedXml;
    }

    /**
     * Posts the signed seed XML to DGII and extracts the auth token.
     *
     * @throws \RuntimeException
     */
    private function validateSignedSeed(string $signedXml): string
    {
        $url = DgiiEndpoints::getAutenticacionBaseUrl($this->ambiente).'/ValidarSemilla';

        $tmpFile = tempnam(sys_get_temp_dir(), 'fe_seed_').'.xml';
        file_put_contents($tmpFile, $signedXml);

        $start = hrtime(true);

        Log::info('[DgiiAuth] POST ValidarSemilla', ['url' => $url]);

        try {
            $response = Http::timeout($this->timeout)
                ->accept('application/json')
                ->attach('xml', file_get_contents($tmpFile), 'response_'.time().'-signed.xml')
                ->post($url);
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            $this->audit('validate_seed', null, ['url' => $url], null, 0, $e->getMessage());
            throw new \RuntimeException('Error conectando a DGII (ValidarSemilla): '.$e->getMessage(), 0, $e);
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        if (! $response->successful()) {
            $this->audit('validate_seed', null, ['url' => $url], ['body' => $response->body()], $response->status(), 'HTTP '.$response->status(), $durationMs);
            throw new \RuntimeException('DGII ValidarSemilla HTTP '.$response->status().': '.$response->body());
        }

        $token = $this->extractToken($response->body());

        if ($token === null) {
            $this->audit('validate_seed', null, ['url' => $url], ['body' => substr($response->body(), 0, 500)], $response->status(), 'Token not found in response', $durationMs);
            throw new \RuntimeException('No se pudo extraer token de la respuesta de DGII. Body: '.substr($response->body(), 0, 500));
        }

        $this->audit('validate_seed', null, ['url' => $url], ['token_prefix' => substr($token, 0, 8).'...'], $response->status(), null, $durationMs);

        return $token;
    }

    /**
     * Extracts the auth token from DGII response (handles multiple response shapes).
     */
    private function extractToken(string $responseBody): ?string
    {
        // Attempt JSON first
        $decoded = json_decode($responseBody, true);

        if (is_array($decoded)) {
            foreach (['token', 'Token', 'access_token'] as $key) {
                if (! empty($decoded[$key])) {
                    return $decoded[$key];
                }
            }

            if (! empty($decoded['data']['token'])) {
                return $decoded['data']['token'];
            }

            if (! empty($decoded['data']['Token'])) {
                return $decoded['data']['Token'];
            }
        }

        // Attempt XML
        $xml = @simplexml_load_string($responseBody);

        if ($xml !== false) {
            $token = (string) ($xml->token ?? $xml->Token ?? '');

            if (! empty($token)) {
                return $token;
            }
        }

        // Plain string token
        $trimmed = trim($responseBody, '"\'');

        if (strlen($trimmed) > 20 && ! str_contains($trimmed, ' ')) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Records an action in the ECF audit log.
     */
    private function audit(
        string $action,
        ?int $ecfId,
        ?array $payload,
        ?array $response,
        ?int $statusCode,
        ?string $error = null,
        ?int $durationMs = null
    ): void {
        try {
            EcfAuditLog::create([
                'business_id' => $this->business->id,
                'ecf_id' => $ecfId,
                'action' => $action,
                'payload' => $payload,
                'response' => $response,
                'status_code' => $statusCode,
                'error' => $error,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DgiiAuth] Failed to write audit log', ['error' => $e->getMessage()]);
        }
    }
}
