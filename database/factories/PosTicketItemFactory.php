<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PosTicket;
use App\Models\PosTicketItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosTicketItem>
 */
class PosTicketItemFactory extends Factory
{
    protected $model = PosTicketItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 50, 2000);
        $qty = fake()->numberBetween(1, 3);

        return [
            'pos_ticket_id' => PosTicket::factory(),
            'item_type' => fake()->randomElement(['service', 'product']),
            'item_id' => fake()->numberBetween(1, 100),
            'name' => fake()->words(2, true),
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
            'employee_id' => null,
            'appointment_service_id' => null,
        ];
    }
}
