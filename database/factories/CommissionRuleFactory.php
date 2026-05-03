<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'employee_id' => null,
            'service_id' => null,
            'type' => fake()->randomElement(['percentage', 'fixed']),
            'value' => fake()->randomFloat(2, 5, 50),
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
            'effective_from' => null,
            'effective_until' => null,
        ];
    }

    /**
     * Percentage-based commission rule.
     */
    public function percentage(float $value = 10.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $value,
        ]);
    }

    /**
     * Fixed-amount commission rule.
     */
    public function fixed(float $value = 5.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $value,
        ]);
    }

    /**
     * Scope rule to a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'business_id' => $employee->business_id,
        ]);
    }

    /**
     * Scope rule to a specific service.
     */
    public function forService(Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $service->id,
            'business_id' => $service->business_id,
        ]);
    }

    /**
     * Scope rule to a specific employee and service combination.
     */
    public function forEmployeeAndService(Employee $employee, Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'service_id' => $service->id,
            'business_id' => $employee->business_id,
        ]);
    }

    /**
     * Mark rule as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Rule with past effective_until date (expired).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now()->subMonths(3)->toDateString(),
            'effective_until' => now()->subMonth()->toDateString(),
        ]);
    }
}
