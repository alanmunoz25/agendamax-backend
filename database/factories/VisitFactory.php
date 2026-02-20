<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'client_id' => User::factory()->client(),
            'employee_id' => Employee::factory(),
            'appointment_id' => Appointment::factory(),
            'verified_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'qr_code' => Str::random(32),
            'stamp_awarded' => false,
        ];
    }
}
