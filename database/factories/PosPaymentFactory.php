<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PosPayment;
use App\Models\PosTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosPayment>
 */
class PosPaymentFactory extends Factory
{
    protected $model = PosPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_ticket_id' => PosTicket::factory(),
            'method' => fake()->randomElement(['cash', 'card', 'transfer']),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'reference' => null,
            'cash_tendered' => null,
            'cash_change' => null,
        ];
    }
}
