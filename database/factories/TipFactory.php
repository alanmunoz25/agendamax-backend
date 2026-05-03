<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Tip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tip>
 */
class TipFactory extends Factory
{
    protected $model = Tip::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = Business::factory()->create();
        $employee = Employee::factory()->create(['business_id' => $business->id]);
        $appointment = Appointment::factory()->create([
            'business_id' => $business->id,
            'employee_id' => $employee->id,
        ]);

        return [
            'business_id' => $business->id,
            'appointment_id' => $appointment->id,
            'employee_id' => $employee->id,
            'payroll_period_id' => null,
            'amount' => fake()->randomFloat(2, 5, 50),
            'payment_method' => 'cash',
            'notes' => fake()->optional()->sentence(),
            'received_at' => now(),
        ];
    }

    /**
     * Cash tip (default).
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Card tip.
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'card',
        ]);
    }

    /**
     * Tip linked to a specific appointment.
     */
    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_id' => $appointment->id,
            'business_id' => $appointment->business_id,
        ]);
    }

    /**
     * Tip assigned to a specific payroll period.
     */
    public function inPeriod(PayrollPeriod $period): static
    {
        return $this->state(fn (array $attributes) => [
            'payroll_period_id' => $period->id,
        ]);
    }
}
