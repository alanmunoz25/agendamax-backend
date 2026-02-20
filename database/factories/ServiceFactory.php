<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            ['name' => 'Haircut', 'duration' => 30, 'price' => 25.00, 'category' => 'Hair'],
            ['name' => 'Hair Coloring', 'duration' => 90, 'price' => 80.00, 'category' => 'Hair'],
            ['name' => 'Manicure', 'duration' => 45, 'price' => 30.00, 'category' => 'Nails'],
            ['name' => 'Pedicure', 'duration' => 60, 'price' => 40.00, 'category' => 'Nails'],
            ['name' => 'Facial Treatment', 'duration' => 75, 'price' => 65.00, 'category' => 'Skin'],
            ['name' => 'Massage', 'duration' => 60, 'price' => 70.00, 'category' => 'Wellness'],
            ['name' => 'Beard Trim', 'duration' => 20, 'price' => 15.00, 'category' => 'Hair'],
        ];

        $service = fake()->randomElement($services);

        return [
            'business_id' => Business::factory(),
            'name' => $service['name'],
            'description' => fake()->sentence(),
            'duration' => $service['duration'],
            'price' => $service['price'],
            'category' => $service['category'],
            'service_category_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the service is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
