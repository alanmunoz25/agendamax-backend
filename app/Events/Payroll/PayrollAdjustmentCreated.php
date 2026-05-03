<?php

declare(strict_types=1);

namespace App\Events\Payroll;

use App\Models\PayrollAdjustment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollAdjustmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PayrollAdjustment $adjustment
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('employee.'.$this->adjustment->employee_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payroll.adjustment.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $period = $this->adjustment->period;
        $periodName = self::periodName($period->starts_on->month, $period->starts_on->year);
        $isCompensation = str_starts_with((string) $this->adjustment->reason, 'Void compensation:');
        $typeLabel = $this->adjustment->type === 'debit' ? 'débito' : 'crédito';

        return [
            'title' => 'Ajuste aplicado a tu nómina',
            'body' => "Se aplicó un ajuste {$typeLabel} de \${$this->adjustment->amount} a tu nómina de {$periodName}.",
            'data' => [
                'type' => 'payroll_adjustment_created',
                'adjustment_id' => $this->adjustment->id,
                'period_id' => $period->id,
                'period_name' => $periodName,
                'adjustment_type' => $this->adjustment->type,
                'amount' => (string) $this->adjustment->amount,
                'is_compensation' => $isCompensation,
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
