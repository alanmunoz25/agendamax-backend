<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QrCode>
 */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'code' => (string) Str::uuid(),
            'type' => 'visit',
            'reward_description' => fake()->sentence(4),
            'stamps_required' => fake()->numberBetween(5, 15),
            'is_active' => true,
            'image_path' => null,
        ];
    }

    /**
     * Indicate that the QR code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
