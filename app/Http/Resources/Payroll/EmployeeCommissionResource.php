<?php

declare(strict_types=1);

namespace App\Http\Resources\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeCommissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PayrollPeriod|null $period */
        $period = $this->additional['period'] ?? null;

        $isRetroactive = false;
        if ($period && $this->appointment && $this->appointment->scheduled_at) {
            $isRetroactive = $this->appointment->scheduled_at->lt($period->starts_on);
        }

        return [
            'id' => $this->id,
            'service_name' => $this->service?->name ?? '',
            'service_price_snapshot' => (string) $this->service_price_snapshot,
            'commission_amount' => (string) $this->commission_amount,
            'rule_type_snapshot' => $this->rule_type_snapshot,
            'rule_value_snapshot' => (string) $this->rule_value_snapshot,
            'appointment_date' => $this->appointment?->scheduled_at?->toDateString(),
            'status' => $this->status,
            'is_retroactive' => $isRetroactive,
        ];
    }
}
