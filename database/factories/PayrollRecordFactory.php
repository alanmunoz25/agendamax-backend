<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollRecord>
 */
class PayrollRecordFactory extends Factory
{
    protected $model = PayrollRecord::class;

    /**
     * Define the model's default state.
     * Audit/transition fields (status, *_at, *_by, payment_*) are intentionally excluded from fillable.
     * The default afterCreating ensures status='draft' is visible on the in-memory instance.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = Business::factory()->create();

        return [
            'business_id' => $business->id,
            'payroll_period_id' => PayrollPeriod::factory()->forBusiness($business),
            'employee_id' => Employee::factory()->create(['business_id' => $business->id]),
            'base_salary_snapshot' => 0,
            'commissions_total' => 0,
            'tips_total' => 0,
            'adjustments_total' => 0,
            'gross_total' => 0,
            'snapshot_payload' => null,
        ];
    }

    /**
     * After each creation, refresh the model so audit fields set by DB defaults are visible in memory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (PayrollRecord $record): void {
            $record->refresh();
        });
    }

    /**
     * Draft status (default) — explicitly sets draft via forceFill after creation.
     */
    public function draft(): static
    {
        return $this->afterCreating(function (PayrollRecord $record): void {
            // Bypass fillable: factory state sets audit fields directly for test setup.
            $record->forceFill([
                'status' => 'draft',
                'approved_at' => null,
                'paid_at' => null,
                'voided_at' => null,
            ])->save();
        });
    }

    /**
     * Approved status — sets approved_at and approved_by via forceFill.
     */
    public function approved(): static
    {
        return $this->afterCreating(function (PayrollRecord $record): void {
            $approver = User::factory()->create(['business_id' => $record->business_id]);
            // Bypass fillable: factory state sets audit fields directly for test setup.
            $record->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ])->save();
        });
    }

    /**
     * Paid status — sets approved and paid audit fields via forceFill.
     */
    public function paid(): static
    {
        return $this->afterCreating(function (PayrollRecord $record): void {
            $approver = User::factory()->create(['business_id' => $record->business_id]);
            $payer = User::factory()->create(['business_id' => $record->business_id]);
            // Bypass fillable: factory state sets audit fields directly for test setup.
            $record->forceFill([
                'status' => 'paid',
                'approved_at' => now()->subDay(),
                'approved_by' => $approver->id,
                'paid_at' => now(),
                'paid_by' => $payer->id,
                'payment_method' => fake()->randomElement(['cash', 'transfer', 'check']),
            ])->save();
        });
    }

    /**
     * Voided status — sets voided audit fields via forceFill.
     */
    public function voided(): static
    {
        return $this->afterCreating(function (PayrollRecord $record): void {
            $voider = User::factory()->create(['business_id' => $record->business_id]);
            // Bypass fillable: factory state sets audit fields directly for test setup.
            $record->forceFill([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $voider->id,
                'void_reason' => fake()->sentence(),
            ])->save();
        });
    }
}
