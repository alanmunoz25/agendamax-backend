<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PosTicket;
use App\Services\ElectronicInvoice\ElectronicInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmitEcfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum attempts before marking the job as permanently failed. */
    public int $tries = 3;

    /** Seconds to wait between retries; DGII auth service also retries internally. */
    public int $backoff = 60;

    public function __construct(public readonly int $ticketId) {}

    /**
     * Execute the job — full DGII e-CF emission pipeline.
     */
    public function handle(ElectronicInvoiceService $service): void
    {
        $ticket = PosTicket::withoutGlobalScopes()
            ->with(['items', 'business.feConfig'])
            ->find($this->ticketId);

        if (! $ticket) {
            Log::warning('EmitEcfJob: ticket not found, skipping', ['ticket_id' => $this->ticketId]);

            return;
        }

        // Idempotency: skip if an NCF was already assigned to this ticket
        if (! empty($ticket->ecf_ncf)) {
            Log::info('EmitEcfJob skipped: ticket already has e-CF', [
                'ticket_id' => $ticket->id,
                'ecf_ncf' => $ticket->ecf_ncf,
            ]);

            return;
        }

        // Verify feConfig is active for this business
        $config = $ticket->business?->feConfig;

        if (! $config || ! $config->activo) {
            Log::warning('EmitEcfJob aborted: business feConfig inactive or missing', [
                'ticket_id' => $ticket->id,
                'business_id' => $ticket->business_id,
            ]);

            // forceFill — ecf_status excluded from $fillable (PosTicket hardening)
            $ticket->forceFill(['ecf_status' => 'error'])->save();

            return;
        }

        // Build service bound to this business
        $boundService = new ElectronicInvoiceService($ticket->business, $config);

        $ecf = $boundService->emitirDesdePosTicket($ticket);

        // Associate NCF to ticket — use forceFill: ecf_ncf and ecf_status are
        // excluded from $fillable per mass-assignment hardening (BLOCK-008)
        $ticket->forceFill([
            'ecf_ncf' => $ecf->numero_ecf,
            'ecf_status' => 'emitted',
            'ecf_emitted_at' => now(),
        ])->save();

        Log::info('EmitEcfJob completed', [
            'ticket_id' => $ticket->id,
            'ecf_numero_ecf' => $ecf->numero_ecf,
            'ecf_id' => $ecf->id,
        ]);
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('EmitEcfJob failed permanently', [
            'ticket_id' => $this->ticketId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $ticket = PosTicket::withoutGlobalScopes()->find($this->ticketId);

        if ($ticket) {
            // forceFill — ecf_status and ecf_error_message excluded from $fillable (BLOCK-008)
            $ticket->forceFill([
                'ecf_status' => 'error',
                'ecf_error_message' => substr($exception->getMessage(), 0, 500),
            ])->save();
        }

        // TODO v1.05: dispatch admin push notification via Reverb
    }
}
