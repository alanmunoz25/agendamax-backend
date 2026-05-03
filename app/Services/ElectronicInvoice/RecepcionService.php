<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\EcfAuditLog;
use App\Models\EcfReceived;
use DOMDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecepcionService
{
    private readonly XmlSignerService $signerService;

    public function __construct(
        private readonly Business $business,
        private readonly BusinessFeConfig $feConfig
    ) {
        $this->signerService = new XmlSignerService($business, $feConfig);
    }

    /**
     * Processes a received e-CF XML:
     *   parse → persist → generate ARECF → sign ARECF → return signed ARECF XML.
     *
     * @throws \InvalidArgumentException when parsed RNCComprador does not match our RNC
     * @throws \RuntimeException on signing failure
     */
    public function receive(string $xmlString, int $estado = 0, ?string $codigoMotivo = null): EcfReceived
    {
        Log::info('[Recepcion] Receiving e-CF', ['business_id' => $this->business->id]);

        $data = $this->parseXml($xmlString);

        // Validate that the comprador RNC matches this business
        if (! empty($data['RNCComprador']) && $data['RNCComprador'] !== $this->feConfig->rnc_emisor) {
            Log::warning('[Recepcion] RNCComprador mismatch', [
                'business_id' => $this->business->id,
                'expected' => $this->feConfig->rnc_emisor,
                'received' => $data['RNCComprador'],
            ]);
        }

        // Persist received ECF
        $ecfReceived = $this->persist($data, $xmlString, $estado);

        // Build ARECF XML
        $data['Estado'] = $estado;
        $data['CodigoMotivoNoRecibido'] = $codigoMotivo ?? '';
        $data['FechaHoraAcuseRecibo'] = now()->format('d-m-Y H:i:s');

        $arecfXml = $this->buildArecf($data);

        // Sign ARECF
        $signedArecf = $this->signerService->sign($arecfXml);

        // Persist signed ARECF
        $arecfPath = 'ecf_recibidos/business_'.$this->business->id.'/arecf_'.($data['eNCF'] ?? time()).'.xml';
        Storage::put($arecfPath, $signedArecf);

        $ecfReceived->xml_arecf_path = $arecfPath;
        $ecfReceived->status = $estado === 0 ? 'accepted' : 'rejected';
        $ecfReceived->arecf_sent_at = now();
        $ecfReceived->save();

        $this->audit('receive_ecf', null, ['numero_ecf' => $data['eNCF'] ?? ''], ['arecf_path' => $arecfPath], 200);

        Log::info('[Recepcion] e-CF received and ARECF generated', [
            'business_id' => $this->business->id,
            'numero_ecf' => $data['eNCF'] ?? '',
            'ecf_received_id' => $ecfReceived->id,
        ]);

        return $ecfReceived;
    }

    /**
     * Sends an ACECF (Acuse de Conformidad ECF) approval to DGII and updates the record.
     *
     * Mirrors the receive() flow but with Estado=0 (approved) and without re-persisting
     * the original XML — the EcfReceived is already stored.
     *
     * @param  array{comentario?: string, usuario?: int}  $context
     *
     * @throws \RuntimeException on signing failure or when XML is missing
     */
    public function enviarAcecfAprobacion(EcfReceived $ecfReceived, array $context = []): void
    {
        Log::info('[Recepcion] Sending ACECF approval', [
            'business_id' => $this->business->id,
            'ecf_received_id' => $ecfReceived->id,
            'numero_ecf' => $ecfReceived->numero_ecf,
        ]);

        // Build ACECF data from the stored record
        $data = [
            'RNCEmisor' => $ecfReceived->rnc_emisor ?? '',
            'RNCComprador' => $this->feConfig->rnc_emisor,
            'eNCF' => $ecfReceived->numero_ecf ?? '',
            'Estado' => 0, // 0 = accepted / conformidad
            'CodigoMotivoNoRecibido' => '',
            'FechaHoraAcuseRecibo' => now()->format('d-m-Y H:i:s'),
        ];

        $arecfXml = $this->buildArecf($data);
        $signedArecf = $this->signerService->sign($arecfXml);

        $arecfPath = 'ecf_recibidos/business_'.$this->business->id.'/acecf_aprobacion_'.($ecfReceived->numero_ecf ?? time()).'.xml';
        Storage::put($arecfPath, $signedArecf);

        $ecfReceived->xml_arecf_path = $arecfPath;
        $ecfReceived->arecf_sent_at = now();
        $ecfReceived->save();

        $this->audit('send_acecf_aprobacion', null, [
            'numero_ecf' => $ecfReceived->numero_ecf,
            'usuario' => $context['usuario'] ?? null,
            'comentario' => $context['comentario'] ?? null,
        ], ['arecf_path' => $arecfPath], 200);
    }

    /**
     * Returns the signed ARECF XML for the given EcfReceived record.
     *
     * @throws \RuntimeException when ARECF path is missing
     */
    public function getSignedArecf(EcfReceived $ecfReceived): string
    {
        if (empty($ecfReceived->xml_arecf_path)) {
            throw new \RuntimeException("EcfReceived #{$ecfReceived->id} has no ARECF path.");
        }

        return Storage::get($ecfReceived->xml_arecf_path) ?? throw new \RuntimeException('ARECF file not found.');
    }

    /**
     * Parses an ECF XML string and extracts the relevant fields.
     *
     * @return array<string, string>
     *
     * @throws \RuntimeException on invalid XML
     */
    private function parseXml(string $xmlString): array
    {
        $xml = @simplexml_load_string($xmlString);

        if ($xml === false) {
            throw new \RuntimeException('RecepcionService: invalid XML received.');
        }

        return [
            'RNCEmisor' => (string) ($xml->Encabezado->Emisor->RNCEmisor ?? ''),
            'RazonSocialEmisor' => (string) ($xml->Encabezado->Emisor->RazonSocialEmisor ?? ''),
            'NombreComercial' => (string) ($xml->Encabezado->Emisor->NombreComercial ?? ''),
            'CorreoEmisor' => (string) ($xml->Encabezado->Emisor->CorreoEmisor ?? ''),
            'RNCComprador' => (string) ($xml->Encabezado->Comprador->RNCComprador ?? ''),
            'RazonSocialComprador' => (string) ($xml->Encabezado->Comprador->RazonSocialComprador ?? ''),
            'eNCF' => (string) ($xml->Encabezado->IdDoc->eNCF ?? ''),
            'TipoeCF' => (string) ($xml->Encabezado->IdDoc->TipoeCF ?? ''),
            'FechaEmision' => (string) ($xml->Encabezado->Emisor->FechaEmision ?? ''),
            'MontoTotal' => (string) ($xml->Encabezado->Totales->MontoTotal ?? '0'),
            'ITBIS' => (string) ($xml->Encabezado->Totales->TotalITBIS ?? '0'),
        ];
    }

    /**
     * Persists the parsed data as an EcfReceived record.
     *
     * @param  array<string, string>  $data
     */
    private function persist(array $data, string $xmlString, int $estado): EcfReceived
    {
        $xmlPath = 'ecf_recibidos/business_'.$this->business->id.'/ecf_'.($data['eNCF'] ?? time()).'.xml';
        Storage::put($xmlPath, $xmlString);

        return EcfReceived::create([
            'business_id' => $this->business->id,
            'rnc_emisor' => $data['RNCEmisor'],
            'razon_social_emisor' => $data['RazonSocialEmisor'],
            'nombre_comercial_emisor' => $data['NombreComercial'],
            'correo_emisor' => $data['CorreoEmisor'],
            'numero_ecf' => $data['eNCF'],
            'tipo' => $data['TipoeCF'] ?: null,
            'fecha_emision' => ! empty($data['FechaEmision']) ? $this->parseDate($data['FechaEmision']) : null,
            'monto_total' => (float) $data['MontoTotal'],
            'itbis_total' => (float) $data['ITBIS'],
            'xml_path' => $xmlPath,
            'status' => $estado === 0 ? 'pending' : 'rejected',
            'codigo_motivo' => null,
        ]);
    }

    /**
     * Builds the ARECF XML (Acuse de Recibo ECF).
     *
     * @param  array<string, string>  $data
     */
    private function buildArecf(array $data): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('ARECF');
        $dom->appendChild($root);

        $detalle = $dom->createElement('DetalleAcusedeRecibo');
        $root->appendChild($detalle);

        $this->el($dom, $detalle, 'Version', '1.0');
        $this->el($dom, $detalle, 'RNCEmisor', $data['RNCEmisor'] ?? '');
        $this->el($dom, $detalle, 'RNCComprador', $data['RNCComprador'] ?? $this->feConfig->rnc_emisor);
        $this->el($dom, $detalle, 'eNCF', $data['eNCF'] ?? '');
        $this->el($dom, $detalle, 'Estado', (string) ($data['Estado'] ?? '0'));

        if (! empty($data['CodigoMotivoNoRecibido']) && ($data['Estado'] ?? '0') == '1') {
            $this->el($dom, $detalle, 'CodigoMotivoNoRecibido', $data['CodigoMotivoNoRecibido']);
        }

        $this->el($dom, $detalle, 'FechaHoraAcuseRecibo', $data['FechaHoraAcuseRecibo'] ?? now()->format('d-m-Y H:i:s'));

        return $dom->saveXML();
    }

    /**
     * Parses a DGII date string (dd-mm-yyyy) into a format MySQL accepts.
     */
    private function parseDate(string $date): ?string
    {
        $dt = \DateTime::createFromFormat('d-m-Y', $date);

        return $dt !== false ? $dt->format('Y-m-d') : null;
    }

    private function el(DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
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
            Log::error('[Recepcion] Failed to write audit log', ['error' => $e->getMessage()]);
        }
    }
}
