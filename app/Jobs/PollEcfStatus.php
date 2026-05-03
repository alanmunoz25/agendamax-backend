<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ecf;
use App\Models\EcfAuditLog;
use App\Services\ElectronicInvoice\ElectronicInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollEcfStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts (matches the 4-step backoff).
     */
    public int $tries = 4;

    /**
     * Maximum lifetime of a job in seconds (24 hours).
     */
    public int $timeout = 86400;

    public function __construct(public readonly int $ecfId) {}

    /**
     * Backoff intervals in seconds between polling attempts:
     *   5 min, 15 min, 1 hour, 4 hours.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('electronic-invoice.poll_backoff', [300, 900, 3600, 14400]);
    }

    /**
     * Handle the job.
     */
    public function handle(): void
    {
        $ecf = Ecf::withoutGlobalScopes()->find($this->ecfId);

        if ($ecf === null) {
            Log::warning('[PollEcfStatus] ECF not found', ['ecf_id' => $this->ecfId]);

            return;
        }

        if ($ecf->isTerminalStatus()) {
            Log::info('[PollEcfStatus] ECF already in terminal status — skipping', [
                'ecf_id' => $this->ecfId,
                'status' => $ecf->status,
            ]);

            return;
        }

        if (empty($ecf->track_id)) {
            Log::warning('[PollEcfStatus] ECF has no track_id', ['ecf_id' => $this->ecfId]);

            return;
        }

        $business = $ecf->business()->withoutGlobalScopes()->first();

        if ($business === null) {
            Log::error('[PollEcfStatus] Business not found', ['ecf_id' => $this->ecfId]);

            return;
        }

        $feConfig = $business->feConfig()->withoutGlobalScopes()->first();

        if ($feConfig === null) {
            Log::error('[PollEcfStatus] BusinessFeConfig not found', [
                'ecf_id' => $this->ecfId,
                'business_id' => $business->id,
            ]);

            return;
        }

        Log::info('[PollEcfStatus] Polling DGII status', [
            'ecf_id' => $this->ecfId,
            'track_id' => $ecf->track_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            $service = new ElectronicInvoiceService($business, $feConfig);
            $result = $service->queryStatus($ecf);

            $ecf->status = $result['status'];
            $ecf->last_polled_at = now();
            $ecf->error_message = $result['status'] === 'rejected' ? ($result['mensaje'] ?: null) : null;
            $ecf->save();

            Log::info('[PollEcfStatus] Status updated', [
                'ecf_id' => $this->ecfId,
                'status' => $result['status'],
                'mensaje' => $result['mensaje'],
            ]);

            EcfAuditLog::create([
                'business_id' => $ecf->business_id,
                'ecf_id' => $ecf->id,
                'action' => 'poll_status_resolved',
                'payload' => ['track_id' => $ecf->track_id, 'attempt' => $this->attempts()],
                'response' => $result['response'],
                'status_code' => 200,
                'error' => null,
                'duration_ms' => null,
            ]);

            // If still not resolved, the retry backoff will re-queue automatically
        } catch (\Throwable $e) {
            Log::error('[PollEcfStatus] Error polling status', [
                'ecf_id' => $this->ecfId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('[PollEcfStatus] Job failed after all retries', [
            'ecf_id' => $this->ecfId,
            'error' => $e->getMessage(),
        ]);

        $ecf = Ecf::withoutGlobalScopes()->find($this->ecfId);

        if ($ecf !== null && ! $ecf->isTerminalStatus()) {
            $ecf->error_message = 'Polling agotado: '.$e->getMessage();
            $ecf->save();

            EcfAuditLog::create([
                'business_id' => $ecf->business_id,
                'ecf_id' => $ecf->id,
                'action' => 'poll_status_exhausted',
                'payload' => ['track_id' => $ecf->track_id],
                'response' => null,
                'status_code' => 0,
                'error' => $e->getMessage(),
                'duration_ms' => null,
            ]);
        }
    }
}
