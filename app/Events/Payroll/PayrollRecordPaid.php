<?php

declare(strict_types=1);

namespace App\Events\Payroll;

use App\Models\PayrollRecord;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollRecordPaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PayrollRecord $record
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('employee.'.$this->record->employee_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payroll.record.paid';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $period = $this->record->period;
        $periodName = self::periodName($period->starts_on->month, $period->starts_on->year);
        $gross = (string) $this->record->gross_total;

        return [
            'title' => 'Pago de nómina procesado',
            'body' => "Tu pago de {$periodName} (\${$gross}) fue registrado.",
            'data' => [
                'type' => 'payroll_record_paid',
                'record_id' => $this->record->id,
                'period_id' => $period->id,
                'period_name' => $periodName,
                'gross_total' => $gross,
                'payment_method' => $this->record->payment_method,
            ],
        ];
    }

    private static function periodName(int $month, int $year): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[$month].' '.$year;
    }
}
