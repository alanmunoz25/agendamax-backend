<?php

declare(strict_types=1);

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAdjustmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isCompensation = str_starts_with((string) $this->reason, 'Void compensation:');

        return [
            'id' => $this->id,
            'period' => [
                'id' => $this->payroll_period_id,
                'starts_on' => $this->period?->starts_on?->toDateString(),
                'ends_on' => $this->period?->ends_on?->toDateString(),
            ],
            'type' => $this->type,
            'amount' => (string) $this->amount,
            'signed_amount' => $this->signedAmount(),
            'reason' => $this->reason,
            'description' => $this->description,
            'is_compensation' => $isCompensation,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
