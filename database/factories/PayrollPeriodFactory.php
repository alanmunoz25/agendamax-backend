<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    /**
     * Define the model's default state.
     * closed_at and closed_by are excluded (not fillable); use the closed() state for those.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = now()->startOfMonth();

        return [
            'business_id' => Business::factory(),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->copy()->endOfMonth()->toDateString(),
            'status' => 'open',
        ];
    }

    /**
     * Open payroll period (default state).
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
        ]);
    }

    /**
     * Closed payroll period — sets status, closed_at via forceFill (audit fields not in fillable).
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ])->afterCreating(function (PayrollPeriod $period): void {
            // Bypass fillable: factory state sets audit fields directly for test setup.
            $period->forceFill([
                'closed_at' => now(),
                'closed_by' => null,
            ])->save();
        });
    }

    /**
     * Period for a specific calendar month.
     */
    public function forMonth(Carbon $month): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => $month->copy()->startOfMonth()->toDateString(),
            'ends_on' => $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Period for a specific business.
     */
    public function forBusiness(Business $business): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => $business->id,
        ]);
    }
}
