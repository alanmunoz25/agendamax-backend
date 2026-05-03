<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

use App\Jobs\PollEcfStatus;
use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\EcfAuditLog;
use App\Models\NcfRango;
use App\Models\PosTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ElectronicInvoiceService
{
    private readonly DgiiAuthService $authService;

    private readonly XmlSignerService $signerService;

    private readonly int $timeout;

    public function __construct(
        private readonly Business $business,
        private readonly BusinessFeConfig $feConfig
    ) {
        $this->authService = new DgiiAuthService($business, $feConfig);
        $this->signerService = new XmlSignerService($business, $feConfig);
        $this->timeout = config('electronic-invoice.dgii_timeout', 30);
    }

    /**
     * Full e-CF emission pipeline:
     *   generate XML → sign → send to DGII → persist trackId → dispatch PollEcfStatus job.
     *
     * The $xmlGenerator is passed in to make this method testable.
     *
     * @param  array<int, array{name: string, quantity: float, unit_price: float, discount?: float, indicator?: string}>  $items
     * @param  array<string, mixed>  $options
     *
     * @throws \InvalidArgumentException on business_id mismatch
     * @throws \RuntimeException on DGII communication failure
     */
    public function emit(Ecf $ecf, array $items, array $options = []): void
    {
        if ($ecf->business_id !== $this->business->id) {
            throw new \InvalidArgumentException(
                "Ecf business_id ({$ecf->business_id}) mismatch with service business_id ({$this->business->id})."
            );
        }

        Log::info('[ElectronicInvoice] Starting emission', [
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'numero_ecf' => $ecf->numero_ecf,
        ]);

        $ecf->status = 'draft';
        $ecf->save();

        // Step 1: Generate XML
        $generator = new XmlGeneratorService($this->business, $this->feConfig);
        $xml = $generator->generate($ecf, $items, $options);

        $this->audit('generate_xml', $ecf->id, ['numero_ecf' => $ecf->numero_ecf], ['xml_length' => strlen($xml)], 200);

        // Step 2: Sign XML
        $signedXml = $this->signerService->sign($xml);
        $ecf->status = 'signed';

        $this->audit('sign_xml', $ecf->id, ['numero_ecf' => $ecf->numero_ecf], ['signed_length' => strlen($signedXml)], 200);

        // Step 3: Persist signed XML to storage
        $xmlPath = $this->persistXml($ecf, $signedXml);
        $ecf->xml_path = $xmlPath;
        $ecf->save();

        // Step 4: Authenticate with DGII (cached token)
        $token = $this->authService->getToken();

        // Step 5: Send to DGII
        $trackId = $this->sendToDgii($ecf, $signedXml, $token);

        $ecf->status = 'sent';
        $ecf->track_id = $trackId;
        $ecf->save();

        Log::info('[ElectronicInvoice] ECF sent to DGII', [
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'track_id' => $trackId,
        ]);

        // Step 6: Dispatch polling job
        PollEcfStatus::dispatch($ecf->id)->delay(
            now()->addSeconds(config('electronic-invoice.poll_backoff.0', 300))
        );
    }

    /**
     * Sends a signed XML to DGII and returns the trackId.
     *
     * @throws \RuntimeException on communication failure
     */
    private function sendToDgii(Ecf $ecf, string $signedXml, string $token): string
    {
        $ambiente = $this->feConfig->ambiente;
        $endpoint = DgiiEndpoints::getRecepcionEndpoint($ambiente);

        $filename = $this->feConfig->rnc_emisor.$ecf->numero_ecf.'.xml';

        $tmpFile = tempnam(sys_get_temp_dir(), 'fe_ecf_').'.xml';
        file_put_contents($tmpFile, $signedXml);

        $start = hrtime(true);

        Log::info('[ElectronicInvoice] POST to DGII', [
            'endpoint' => $endpoint,
            'filename' => $filename,
            'ecf_id' => $ecf->id,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($token)
                ->accept('application/json')
                ->attach('xml', file_get_contents($tmpFile), $filename)
                ->post($endpoint);
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            $this->audit('send_ecf', $ecf->id, ['endpoint' => $endpoint], null, 0, $e->getMessage());
            throw new \RuntimeException('Error enviando e-CF a DGII: '.$e->getMessage(), 0, $e);
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);

        $body = $response->json() ?? ['raw' => $response->body()];

        $this->audit('send_ecf', $ecf->id, ['endpoint' => $endpoint, 'filename' => $filename], $body, $response->status(), $response->successful() ? null : 'HTTP '.$response->status(), $durationMs);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'DGII recepción HTTP '.$response->status().': '.$response->body()
            );
        }

        $trackId = $this->extractTrackId($response->body());

        if (empty($trackId)) {
            Log::warning('[ElectronicInvoice] DGII did not return a trackId', [
                'ecf_id' => $ecf->id,
                'response' => substr($response->body(), 0, 500),
            ]);

            $trackId = 'PENDING_'.$ecf->numero_ecf;
        }

        return $trackId;
    }

    /**
     * Queries DGII for the current status of an ECF by trackId.
     *
     * @return array{status: string, mensaje: string, response: array<string, mixed>}
     */
    public function queryStatus(Ecf $ecf): array
    {
        if ($ecf->business_id !== $this->business->id) {
            throw new \InvalidArgumentException(
                "Ecf business_id ({$ecf->business_id}) mismatch with service business_id ({$this->business->id})."
            );
        }

        $endpoint = DgiiEndpoints::getConsultaEstadoEndpoint($this->feConfig->ambiente);
        $token = $this->authService->getToken();

        $start = hrtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($token)
                ->accept('application/json')
                ->get($endpoint, ['TrackId' => $ecf->track_id]);
        } catch (\Throwable $e) {
            $this->audit('poll_status', $ecf->id, ['track_id' => $ecf->track_id], null, 0, $e->getMessage());
            throw new \RuntimeException('Error consultando estado en DGII: '.$e->getMessage(), 0, $e);
        }

        $durationMs = (int) ((hrtime(true) - $start) / 1e6);
        $body = $response->json() ?? [];

        $this->audit('poll_status', $ecf->id, ['track_id' => $ecf->track_id], $body, $response->status(), $response->successful() ? null : 'HTTP '.$response->status(), $durationMs);

        $status = $this->mapDgiiStatus($body);
        $mensaje = $body['mensaje'] ?? $body['Message'] ?? $body['message'] ?? '';

        return [
            'status' => $status,
            'mensaje' => $mensaje,
            'response' => $body,
        ];
    }

    /**
     * Persists signed XML to business-scoped storage.
     * Returns the relative storage path.
     */
    private function persistXml(Ecf $ecf, string $signedXml): string
    {
        $path = 'ecf_enviados/business_'.$this->business->id.'/'.$ecf->numero_ecf.'.xml';
        Storage::put($path, $signedXml);

        return $path;
    }

    /**
     * Extracts trackId from DGII response body (handles multiple shapes).
     */
    private function extractTrackId(string $responseBody): string
    {
        $decoded = json_decode($responseBody, true);

        if (is_array($decoded)) {
            foreach (['trackId', 'TrackId', 'trackid', 'track_id'] as $key) {
                if (! empty($decoded[$key])) {
                    return (string) $decoded[$key];
                }
            }
        }

        return '';
    }

    /**
     * Maps a DGII JSON response to a normalised status string.
     */
    private function mapDgiiStatus(array $response): string
    {
        $raw = strtolower($response['estado'] ?? $response['Estado'] ?? $response['status'] ?? '');

        return match (true) {
            str_contains($raw, 'aceptado') => 'accepted',
            str_contains($raw, 'rechazado') => 'rejected',
            str_contains($raw, 'procesando') => 'sent',
            str_contains($raw, 'contingencia') => 'contingency',
            default => 'sent',
        };
    }

    /**
     * Creates and emits an e-CF from a POS ticket.
     *
     * Maps ticket data → Ecf record → calls emit() → returns persisted Ecf.
     *
     * @throws \RuntimeException when no active NCF sequence is available for the ticket type
     * @throws \RuntimeException on DGII communication failure
     */
    public function emitirDesdePosTicket(PosTicket $ticket): Ecf
    {
        // Map ecf_type to DGII tipo_ecf code
        $tipoEcf = match ($ticket->ecf_type) {
            'credito_fiscal' => '31',
            'consumidor_final' => '32',
            default => '32',
        };

        // Assign next NCF from sequence
        /** @var NcfRango|null $sequence */
        $sequence = NcfRango::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('tipo_ecf', $tipoEcf)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw new \RuntimeException(
                "No active NCF sequence for tipo_ecf={$tipoEcf} on business_id={$this->business->id}."
            );
        }

        return DB::transaction(function () use ($ticket, $tipoEcf, $sequence): Ecf {
            $numeroEcf = $sequence->assignNextSecuencial();

            // Calculate totals from ticket
            $montoTotal = (float) $ticket->total;
            $itbisTotal = (float) $ticket->itbis_amount;
            $montoGravado = round($montoTotal - $itbisTotal, 2);

            $ecf = Ecf::create([
                'business_id' => $this->business->id,
                'pos_ticket_id' => $ticket->id,
                'numero_ecf' => $numeroEcf,
                'tipo' => $tipoEcf,
                'rnc_comprador' => $ticket->client_rnc,
                'razon_social_comprador' => $ticket->client_name,
                'nombre_comprador' => $ticket->client_name,
                'fecha_emision' => now()->toDateString(),
                'monto_total' => $montoTotal,
                'itbis_total' => $itbisTotal,
                'monto_gravado' => $montoGravado,
            ]);

            // status = 'draft' via forceFill (excluded from $fillable per BLOCK-008 hardening)
            $ecf->forceFill(['status' => 'draft'])->save();

            // Map ticket items to e-CF line items
            $items = $ticket->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => (float) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'discount' => 0.0,
            ])->all();

            $options = [
                'tipo_pago' => 'contado',
                'indicador_monto_gravado' => '1',
            ];

            $this->emit($ecf, $items, $options);

            return $ecf->fresh();
        });
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
            Log::error('[ElectronicInvoice] Failed to write audit log', ['error' => $e->getMessage()]);
        }
    }
}
