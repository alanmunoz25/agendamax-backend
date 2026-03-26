<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Enrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'course_id' => Course::factory(),
            'user_id' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->optional()->phoneNumber(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_provider' => null,
            'payment_reference' => null,
            'payment_metadata' => null,
            'amount_paid' => null,
            'enrolled_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the enrollment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'enrolled_at' => now(),
        ]);
    }
}
