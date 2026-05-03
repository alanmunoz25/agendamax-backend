<?php

declare(strict_types=1);

namespace App\Listeners\Payroll;

use App\Events\Payroll\PayrollRecordApproved;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendPayrollRecordApprovedPush implements ShouldQueue
{
    public string $queue = 'fcm';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly FcmService $fcmService) {}

    public function handle(PayrollRecordApproved $event): void
    {
        $record = $event->record;
        $employee = $record->employee;

        if (! $employee || ! $employee->user) {
            return;
        }

        $period = $record->period;
        $periodName = $this->periodName($period->starts_on->month, $period->starts_on->year);

        $this->fcmService->sendToUser(
            $employee->user,
            'Tu nómina fue aprobada',
            "El período {$periodName} está listo para pago.",
            [
                'type' => 'payroll_record_approved',
                'record_id' => (string) $record->id,
                'period_id' => (string) $period->id,
            ],
        );
    }

    public function failed(PayrollRecordApproved $event, \Throwable $e): void
    {
        Log::error('SendPayrollRecordApprovedPush failed', [
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
