<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Appointment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = fake()->dateTimeBetween('-1 month', '+2 months');
        $duration = fake()->numberBetween(30, 120);

        return [
            'business_id' => Business::factory(),
            'service_id' => Service::factory(),
            'employee_id' => Employee::factory(),
            'client_id' => User::factory()->client(),
            'scheduled_at' => $scheduledAt,
            'scheduled_until' => (clone $scheduledAt)->modify("+{$duration} minutes"),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
            'cancellation_reason' => null,
        ];
    }

    /**
     * Indicate that the appointment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the appointment is completed with payroll fields populated.
     * Sets completed_at to scheduled_until and captures final_price from context.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $scheduledUntil = $attributes['scheduled_until'] ?? fake()->dateTimeBetween('-2 months', '-1 day');

            return [
                'status' => 'completed',
                'scheduled_at' => fake()->dateTimeBetween('-2 months', '-1 day'),
                'completed_at' => $scheduledUntil,
                'final_price' => fake()->randomFloat(2, 20, 200),
            ];
        });
    }

    /**
     * Set a specific final price on the appointment.
     */
    public function withFinalPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'final_price' => $price,
        ]);
    }

    /**
     * Indicate that the appointment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancellation_reason' => fake()->sentence(),
        ]);
    }
}
