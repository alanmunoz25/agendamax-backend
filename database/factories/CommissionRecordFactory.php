<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Pivots\AppointmentService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommissionRecord>
 */
class CommissionRecordFactory extends Factory
{
    protected $model = CommissionRecord::class;

    /**
     * After each creation, refresh the model so transition fields set by DB defaults or afterCreating
     * states (status, payroll_period_id, locked_at, paid_at) are visible in memory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CommissionRecord $record): void {
            $record->refresh();
        });
    }

    /**
     * Define the model's default state.
     * Transition fields (status, payroll_period_id, locked_at, paid_at) are not fillable;
     * they are set via afterCreating states.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $service = Service::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
        ]);

        // Insert pivot row directly to get a FK-able appointment_service_id
        $appointmentServiceId = DB::table('appointment_services')->insertGetId([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $appointmentServiceId,
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'commission_rule_id' => null,
            'service_price_snapshot' => $service->price,
            'rule_type_snapshot' => 'percentage',
            'rule_value_snapshot' => 10.00,
            'commission_amount' => bcmul((string) $service->price, '0.10', 2),
            'generated_at' => now(),
        ];
    }

    /**
     * Pending commission (not yet assigned to a period) — default state after creation.
     */
    public function pending(): static
    {
        return $this->afterCreating(function (CommissionRecord $record): void {
            // Bypass fillable: factory state sets transition fields directly for test setup.
            $record->forceFill([
                'status' => 'pending',
                'payroll_period_id' => null,
                'locked_at' => null,
                'paid_at' => null,
            ])->save();
        });
    }

    /**
     * Locked commission (period has been approved, not yet paid).
     */
    public function locked(): static
    {
        return $this->afterCreating(function (CommissionRecord $record): void {
            // Bypass fillable: factory state sets transition fields directly for test setup.
            $record->forceFill([
                'status' => 'locked',
                'locked_at' => now(),
                'paid_at' => null,
            ])->save();
        });
    }

    /**
     * Paid commission (period has been paid).
     */
    public function paid(): static
    {
        return $this->afterCreating(function (CommissionRecord $record): void {
            // Bypass fillable: factory state sets transition fields directly for test setup.
            $record->forceFill([
                'status' => 'paid',
                'locked_at' => now()->subDay(),
                'paid_at' => now(),
            ])->save();
        });
    }

    /**
     * Voided commission.
     */
    public function voided(): static
    {
        return $this->afterCreating(function (CommissionRecord $record): void {
            // Bypass fillable: factory state sets transition fields directly for test setup.
            $record->forceFill([
                'status' => 'voided',
            ])->save();
        });
    }

    /**
     * Assign commission to a specific appointment service line.
     */
    public function forAppointmentService(AppointmentService $line): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_service_id' => $line->id,
            'appointment_id' => $line->appointment_id,
        ]);
    }

    /**
     * Assign commission to a specific payroll period.
     */
    public function inPeriod(PayrollPeriod $period): static
    {
        return $this->afterCreating(function (CommissionRecord $record) use ($period): void {
            // Bypass fillable: payroll_period_id is a transition field not in fillable.
            $record->forceFill(['payroll_period_id' => $period->id])->save();
        });
    }
}
