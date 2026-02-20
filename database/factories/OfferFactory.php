<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'points_required' => fake()->numberBetween(5, 20),
            'valid_from' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'valid_until' => fake()->dateTimeBetween('+1 month', '+3 months'),
            'is_active' => true,
        ];
    }
}
