<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => Str::random(10),
            'two_factor_recovery_codes' => Str::random(10),
            'two_factor_confirmed_at' => now(),
            'business_id' => null,
            'role' => 'client',
            'phone' => fake()->phoneNumber(),
            'avatar_url' => null,
            'push_token' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model does not have two-factor authentication configured.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => null,
            'role' => 'super_admin',
        ]);
    }

    /**
     * Indicate that the user is a business admin.
     */
    public function businessAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => Business::factory(),
            'role' => 'business_admin',
        ]);
    }

    /**
     * Indicate that the user is an employee.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => Business::factory(),
            'role' => 'employee',
        ]);
    }

    /**
     * Indicate that the user is a client.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => Business::factory(),
            'role' => 'client',
        ]);
    }
}
