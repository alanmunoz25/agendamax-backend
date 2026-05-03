<?php

declare(strict_types=1);

namespace App\Listeners\Payroll;

use App\Events\Payroll\PayrollRecordVoided;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendPayrollRecordVoidedPush implements ShouldQueue
{
    public string $queue = 'fcm';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly FcmService $fcmService) {}

    public function handle(PayrollRecordVoided $event): void
    {
        $record = $event->record;
        $employee = $record->employee;

        if (! $employee || ! $employee->user) {
            return;
        }

        $period = $record->period;
        $periodName = $this->periodName($period->starts_on->month, $period->starts_on->year);
        $gross = (string) $record->gross_total;

        $this->fcmService->sendToUser(
            $employee->user,
            'Tu pago fue anulado',
            "Tu pago de RD$ {$gross} del período {$periodName} fue anulado. Contacta a tu administrador.",
            [
                'type' => 'payroll_voided',
                'record_id' => (string) $record->id,
                'period_id' => (string) $period->id,
            ],
        );
    }

    public function failed(PayrollRecordVoided $event, \Throwable $e): void
    {
        Log::error('SendPayrollRecordVoidedPush failed', [
            'record_id' => $event->record->id,
            'error' => $e->getMessage(),
        ]);
    }

    private function periodName(int $month, int $year): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[$month].' '.$year;
    }
}
