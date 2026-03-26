<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Course::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);
        $startDate = fake()->dateTimeBetween('+1 week', '+3 months');
        $endDate = fake()->dateTimeBetween($startDate, '+6 months');

        return [
            'business_id' => Business::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraphs(3, true),
            'syllabus' => fake()->optional()->paragraphs(2, true),
            'cover_image' => null,
            'instructor_name' => fake()->name(),
            'instructor_bio' => fake()->optional()->sentence(),
            'duration_text' => fake()->randomElement(['4 semanas', '8 semanas', '3 meses', '6 meses']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'enrollment_deadline' => fake()->optional()->dateTimeBetween('now', $startDate),
            'schedule_text' => fake()->randomElement(['Lunes y Miércoles 6-8pm', 'Sábados 9am-12pm', 'Martes y Jueves 7-9pm']),
            'price' => fake()->randomFloat(2, 500, 15000),
            'currency' => 'DOP',
            'capacity' => fake()->optional()->numberBetween(10, 50),
            'modality' => fake()->randomElement(['in-person', 'online', 'hybrid']),
            'is_active' => true,
            'is_featured' => false,
            'meta' => null,
        ];
    }

    /**
     * Indicate that the course is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the course is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
