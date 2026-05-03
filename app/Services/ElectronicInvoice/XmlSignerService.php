<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use DOMDocument;
use Illuminate\Support\Facades\Log;
use Selective\XmlDSig\Algorithm;
use Selective\XmlDSig\CryptoSigner;
use Selective\XmlDSig\PrivateKeyStore;
use Selective\XmlDSig\XmlSigner;

class XmlSignerService
{
    public function __construct(
        private readonly Business $business,
        private readonly BusinessFeConfig $feConfig
    ) {}

    /**
     * Signs the provided XML string using the business P12 certificate stored in the database.
     *
     * @throws \RuntimeException when the certificate is missing, unreadable, or signing fails
     */
    public function sign(string $xmlContent): string
    {
        if (! $this->feConfig->hasCertificate()) {
            throw new \RuntimeException(
                "Certificado no encontrado para business {$this->business->id}: no hay certificado digital convertido."
            );
        }

        $certPassword = $this->feConfig->getDecryptedCertPassword();

        if ($certPassword === null) {
            throw new \RuntimeException(
                "No hay contraseña de certificado configurada para business {$this->business->id}."
            );
        }

        $p12Bytes = $this->feConfig->getCertificateP12();

        Log::info('[XmlSigner] Signing XML', [
            'business_id' => $this->business->id,
            'xml_length' => strlen($xmlContent),
        ]);

        $certs = [];

        if (! openssl_pkcs12_read($p12Bytes, $certs, $certPassword)) {
            $error = openssl_error_string() ?: 'Contraseña incorrecta o certificado corrupto';
            throw new \RuntimeException(
                "Error leyendo PKCS12 (business {$this->business->id}): {$error}"
            );
        }

        $pemContents = $certs['cert'].$certs['pkey'];

        $cleanedXml = $this->canonicalize($xmlContent);

        $privateKeyStore = new PrivateKeyStore;
        $privateKeyStore->loadFromPem($pemContents, $certPassword);
        $privateKeyStore->addCertificatesFromX509Pem($pemContents);

        $algorithm = new Algorithm(Algorithm::METHOD_SHA256);
        $cryptoSigner = new CryptoSigner($privateKeyStore, $algorithm);
        $xmlSigner = new XmlSigner($cryptoSigner);
        $xmlSigner->setReferenceUri('');

        $signedXml = $xmlSigner->signXml($cleanedXml);

        Log::info('[XmlSigner] XML signed successfully', [
            'business_id' => $this->business->id,
            'signed_length' => strlen($signedXml),
        ]);

        return $signedXml;
    }

    /**
     * Returns whether the given XML string contains a Signature node.
     */
    public function isSigned(string $xmlContent): bool
    {
        return str_contains($xmlContent, '<Signature') || str_contains($xmlContent, '<ds:Signature');
    }

    /**
     * Extracts the first 6 characters of the SignatureValue (código de seguridad DGII).
     */
    public function extractCodigoSeguridad(string $signedXml): ?string
    {
        $xml = @simplexml_load_string($signedXml);

        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $nodes = $xml->xpath('//ds:SignatureValue');

        if (empty($nodes)) {
            return null;
        }

        $value = (string) $nodes[0];

        return substr(preg_replace('/\s+/', '', $value), 0, 6);
    }

    /**
     * Cleans and C14N-canonicalises the XML before signing.
     */
    private function canonicalize(string $xml): string
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        return $dom->C14N(true, false);
    }
}
