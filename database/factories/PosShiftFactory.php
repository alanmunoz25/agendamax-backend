<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\PosShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShift>
 */
class PosShiftFactory extends Factory
{
    protected $model = PosShift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cashSales = fake()->randomFloat(2, 500, 5000);
        $cardSales = fake()->randomFloat(2, 500, 5000);
        $transferSales = fake()->randomFloat(2, 0, 2000);
        $totalSales = round($cashSales + $cardSales + $transferSales, 2);
        $openingCash = 500;
        $cashExpected = round($openingCash + $cashSales, 2);
        $cashCounted = $cashExpected;

        return [
            'business_id' => Business::factory(),
            'cashier_id' => User::factory(),
            'shift_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'opened_at' => '09:00',
            'closed_at' => '18:00',
            'opening_cash' => $openingCash,
            'closing_cash_counted' => $cashCounted,
            'closing_cash_expected' => $cashExpected,
            'cash_difference' => 0,
            'difference_reason' => null,
            'tickets_count' => fake()->numberBetween(1, 20),
            'total_sales' => $totalSales,
            'total_tips' => fake()->randomFloat(2, 0, 500),
            'cash_sales' => $cashSales,
            'card_sales' => $cardSales,
            'transfer_sales' => $transferSales,
            'pdf_path' => null,
        ];
    }
}
