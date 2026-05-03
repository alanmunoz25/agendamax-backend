<?php

declare(strict_types=1);

namespace App\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TD-037: N+1 fix — all relations use whenLoaded() and must be eager-loaded by the caller.
 */
class EmployeePayrollRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $gross = (string) $this->gross_total;
        $isNegative = bccomp($gross, '0', 2) < 0;

        return [
            'id' => $this->id,
            'period' => new EmployeePayrollPeriodResource($this->whenLoaded('period')),
            'status' => $this->status,
            'base_salary_snapshot' => (string) $this->base_salary_snapshot,
            'commissions_total' => (string) $this->commissions_total,
            'tips_total' => (string) $this->tips_total,
            'adjustments_total' => (string) $this->adjustments_total,
            'gross_total' => $gross,
            'is_negative' => $isNegative,
            'negative_message' => $isNegative ? 'Tienes un adeudo de períodos anteriores incluido en este período.' : null,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason ?? null,
            'commissions' => $this->when(
                $this->relationLoaded('commissionRecords'),
                fn () => EmployeeCommissionResource::collection($this->commissionRecords)
                    ->additional(['period' => $this->period])
            ),
            'tips' => EmployeeTipResource::collection(
                $this->whenLoaded('tips')
            ),
            'adjustments' => EmployeeAdjustmentResource::collection(
                $this->whenLoaded('adjustments')
            ),
        ];
    }
}
