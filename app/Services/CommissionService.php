<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Resolve the best matching commission rule for a given employee + service combination.
     * Priority order (cascade): employee+service > employee-only > service-only > global default.
     */
    public function resolveRuleFor(Employee $employee, Service $service): ?CommissionRule
    {
        $today = now()->toDateString();

        return CommissionRule::query()
            ->where('business_id', $employee->business_id)
            ->where('is_active', true)
            ->where(function ($q) use ($today): void {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today): void {
                $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today);
            })
            ->where(function ($q) use ($employee, $service): void {
                $q->where(function ($s) use ($employee, $service): void {
                    $s->where('employee_id', $employee->id)->where('service_id', $service->id);
                })->orWhere(function ($s) use ($employee): void {
                    $s->where('employee_id', $employee->id)->whereNull('service_id');
                })->orWhere(function ($s) use ($service): void {
                    $s->whereNull('employee_id')->where('service_id', $service->id);
                })->orWhere(function ($s): void {
                    $s->whereNull('employee_id')->whereNull('service_id');
                });
            })
            ->orderByRaw('
                CASE
                    WHEN employee_id IS NOT NULL AND service_id IS NOT NULL THEN 1
                    WHEN employee_id IS NOT NULL AND service_id IS NULL THEN 2
                    WHEN employee_id IS NULL AND service_id IS NOT NULL THEN 3
                    ELSE 4
                END ASC
            ')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * Generate commission records for all service lines in a completed appointment.
     * Applies Opción A discount proration: each line's effective price = service.price * (final_price / catalogTotal).
     * Lines without an assigned employee are silently skipped (Log::warning).
     *
     * @return Collection<int, CommissionRecord>
     */
    public function generateForAppointment(Appointment $appointment): Collection
    {
        if ($appointment->status !== 'completed') {
            return collect();
        }

        return DB::transaction(function () use ($appointment): Collection {
            $appointment->loadMissing(['services']);
            $lines = $appointment->services;

            $catalogTotal = $lines->sum(fn ($s) => (float) $s->price);
            $finalPrice = $appointment->final_price !== null
                ? (float) $appointment->final_price
                : $catalogTotal;
            $factor = ($catalogTotal > 0 && abs($finalPrice - $catalogTotal) > 0.001)
                ? ($finalPrice / $catalogTotal)
                : 1.0;

            $records = collect();

            // Pre-compute prorated effective prices with residual adjustment on the last line.
            // This guarantees sum(snapshots) === final_price exactly, avoiding floating-point drift.
            $effectivePrices = [];
            if ($factor !== 1.0) {
                $linesWithEmployee = $lines->filter(function ($service) use ($appointment): bool {
                    $employeeId = $service->pivot->employee_id ?? $appointment->employee_id;

                    return $employeeId !== null;
                })->values();

                $count = $linesWithEmployee->count();
                $accumulated = 0.0;

                foreach ($linesWithEmployee as $idx => $service) {
                    if ($idx === $count - 1) {
                        $effectivePrices[$service->pivot->id] = round($finalPrice - $accumulated, 2);
                    } else {
                        $effective = round((float) $service->price * $factor, 2);
                        $effectivePrices[$service->pivot->id] = $effective;
                        $accumulated += $effective;
                    }
                }
            }

            foreach ($lines as $service) {
                $employeeId = $service->pivot->employee_id ?? $appointment->employee_id;

                if ($employeeId === null) {
                    Log::warning('Skipping commission line: no employee assigned', [
                        'appointment_id' => $appointment->id,
                        'appointment_service_id' => $service->pivot->id,
                        'service_id' => $service->id,
                    ]);

                    continue;
                }

                // Sin Auth en queue: bypass scope y validar business_id manualmente
                $employee = Employee::withoutGlobalScopes()->find($employeeId);

                if ($employee === null || $employee->business_id !== $appointment->business_id) {
                    Log::warning('Skipping commission line: employee mismatch', [
                        'appointment_id' => $appointment->id,
                        'employee_id' => $employeeId,
                        'business_id' => $appointment->business_id,
                    ]);

                    continue;
                }

                $rule = $this->resolveRuleFor($employee, $service);
                if ($rule === null) {
                    Log::warning('Skipping commission line: no rule matches', [
                        'appointment_id' => $appointment->id,
                        'employee_id' => $employee->id,
                        'service_id' => $service->id,
                    ]);

                    continue;
                }

                $effectivePrice = $factor !== 1.0
                    ? ($effectivePrices[$service->pivot->id] ?? round((float) $service->price * $factor, 2))
                    : (float) $service->price;
                $commissionAmount = $this->calculateAmount($rule, $effectivePrice);

                $record = CommissionRecord::firstOrCreate(
                    [
                        'appointment_service_id' => $service->pivot->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'business_id' => $appointment->business_id,
                        'appointment_id' => $appointment->id,
                        'service_id' => $service->id,
                        'commission_rule_id' => $rule->id,
                        'service_price_snapshot' => $effectivePrice,
                        'rule_type_snapshot' => $rule->type,
                        'rule_value_snapshot' => $rule->value,
                        'commission_amount' => $commissionAmount,
                        'status' => 'pending',
                        'generated_at' => now(),
                    ]
                );

                $records->push($record);
            }

            return $records;
        });
    }

    /**
     * Generate a commission record for a single service item sold via POS walk-in (no appointment).
     * Looks up the current open payroll period for the employee's business.
     * Silently skips (with Log::info) if no open period exists — no exception is thrown.
     */
    public function generateForServiceItem(
        Employee $employee,
        Service $service,
        string $price,
        ?PayrollPeriod $period
    ): void {
        // Resolve the open period for this business if not provided
        if ($period === null) {
            $period = PayrollPeriod::withoutGlobalScopes()
                ->where('business_id', $employee->business_id)
                ->where('status', 'open')
                ->latest('starts_on')
                ->first();
        }

        if ($period === null) {
            Log::info('No open payroll period for walk-in commission', [
                'employee_id' => $employee->id,
                'business_id' => $employee->business_id,
                'service_id' => $service->id,
            ]);

            return;
        }

        $rule = $this->resolveRuleFor($employee, $service);

        if ($rule === null) {
            Log::warning('Skipping walk-in commission: no rule matches', [
                'employee_id' => $employee->id,
                'service_id' => $service->id,
                'business_id' => $employee->business_id,
            ]);

            return;
        }

        $effectivePrice = (float) $price;
        $commissionAmount = $this->calculateAmount($rule, $effectivePrice);

        CommissionRecord::create([
            'business_id' => $employee->business_id,
            'appointment_id' => null,
            'appointment_service_id' => null,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'commission_rule_id' => $rule->id,
            'payroll_period_id' => $period->id,
            'service_price_snapshot' => $effectivePrice,
            'rule_type_snapshot' => $rule->type,
            'rule_value_snapshot' => $rule->value,
            'commission_amount' => $commissionAmount,
            'status' => 'pending',
            'generated_at' => now(),
        ]);
    }

    /**
     * Calculate the commission amount based on rule type and the effective (prorated) price.
     * Always returns a value rounded to 2 decimal places.
     */
    private function calculateAmount(CommissionRule $rule, float $effectivePrice): float
    {
        return match ($rule->type) {
            'percentage' => round($effectivePrice * ((float) $rule->value / 100), 2),
            'fixed' => round((float) $rule->value, 2),
            default => 0.0,
        };
    }
}
