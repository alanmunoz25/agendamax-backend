<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommissionRecord;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    protected $model = PayrollAdjustment::class;

    /**
     * Define the model's default state.
     * Uses closures so that business_id, employee_id, and created_by all inherit
     * from the same payroll period when states (forPeriod, etc.) override payroll_period_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'business_id' => fn (array $attrs) => PayrollPeriod::find($attrs['payroll_period_id'])->business_id,
            'employee_id' => fn (array $attrs) => Employee::factory()->create(['business_id' => $attrs['business_id']])->id,
            'related_commission_record_id' => null,
            'related_appointment_id' => null,
            'type' => fake()->randomElement(['credit', 'debit']),
            'amount' => fake()->randomFloat(2, 10, 200),
            'reason' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'created_by' => fn (array $attrs) => User::factory()->create(['business_id' => $attrs['business_id']])->id,
        ];
    }

    /**
     * Credit adjustment (adds to employee earnings).
     */
    public function credit(float $amount = 50.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'amount' => $amount,
        ]);
    }

    /**
     * Debit adjustment (subtracts from employee earnings).
     */
    public function debit(float $amount = 50.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'amount' => $amount,
        ]);
    }

    /**
     * Adjustment for a specific payroll period.
     */
    public function forPeriod(PayrollPeriod $period): static
    {
        return $this->state(fn (array $attributes) => [
            'payroll_period_id' => $period->id,
            'business_id' => $period->business_id,
        ]);
    }

    /**
     * Adjustment linked to a specific commission record.
     */
    public function linkedTo(CommissionRecord $commissionRecord): static
    {
        return $this->state(fn (array $attributes) => [
            'related_commission_record_id' => $commissionRecord->id,
        ]);
    }
}
