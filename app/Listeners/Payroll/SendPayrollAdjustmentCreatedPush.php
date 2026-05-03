<?php

declare(strict_types=1);

namespace App\Listeners\Payroll;

use App\Events\Payroll\PayrollAdjustmentCreated;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendPayrollAdjustmentCreatedPush implements ShouldQueue
{
    public string $queue = 'fcm';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly FcmService $fcmService) {}

    public function handle(PayrollAdjustmentCreated $event): void
    {
        $adjustment = $event->adjustment;
        $employee = $adjustment->employee;

        if (! $employee || ! $employee->user) {
            return;
        }

        $amount = (string) $adjustment->amount;
        $reason = (string) $adjustment->reason;

        $body = $adjustment->type === 'credit'
            ? "Se agregaron RD$ {$amount}: {$reason}."
            : "Se descontaron RD$ {$amount}: {$reason}.";

        $this->fcmService->sendToUser(
            $employee->user,
            'Se aplicó un ajuste a tu payroll',
            $body,
            [
                'type' => 'payroll_adjustment',
                'adjustment_id' => (string) $adjustment->id,
                'employee_id' => (string) $adjustment->employee_id,
            ],
        );
    }

    public function failed(PayrollAdjustmentCreated $event, \Throwable $e): void
    {
        Log::error('SendPayrollAdjustmentCreatedPush failed', [
            'adjustment_id' => $event->adjustment->id,
            'error' => $e->getMessage(),
        ]);
    }
}
