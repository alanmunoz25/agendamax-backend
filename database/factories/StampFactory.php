<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class StampFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'client_id' => User::factory()->client(),
            'visit_id' => Visit::factory(),
            'earned_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'redeemed_at' => null,
        ];
    }
}
