<?php

declare(strict_types=1);

namespace App\Events\Payroll;

use App\Models\PayrollRecord;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollRecordVoided implements ShouldBroadcast
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
        return 'payroll.record.voided';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $period = $this->record->period;
        $periodName = self::periodName($period->starts_on->month, $period->starts_on->year);

        return [
            'title' => 'Tu registro de nómina fue anulado',
            'body' => "El registro de {$periodName} fue anulado. Contacta a tu administrador.",
            'data' => [
                'type' => 'payroll_record_voided',
                'record_id' => $this->record->id,
                'period_id' => $period->id,
                'period_name' => $periodName,
                'void_reason' => $this->record->void_reason,
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
