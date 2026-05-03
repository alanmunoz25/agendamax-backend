<?php

declare(strict_types=1);

namespace App\Events\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollPeriodClosed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PayrollPeriod $period
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('business.'.$this->period->business_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payroll.period.closed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $periodName = $months[$this->period->starts_on->month].' '.$this->period->starts_on->year;

        return [
            'event' => 'payroll.period.closed',
            'data' => [
                'type' => 'payroll_period_closed',
                'period_id' => $this->period->id,
                'period_name' => $periodName,
            ],
        ];
    }
}
