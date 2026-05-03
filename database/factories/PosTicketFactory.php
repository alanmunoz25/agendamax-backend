<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosTicket>
 */
class PosTicketFactory extends Factory
{
    protected $model = PosTicket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $discountAmount = 0;
        $itbisPct = 18;
        $itbisAmount = round($subtotal * ($itbisPct / 100), 2);
        $tipAmount = 0;
        $total = round($subtotal - $discountAmount + $itbisAmount + $tipAmount, 2);

        $year = now()->year;

        return [
            'business_id' => Business::factory(),
            'ticket_number' => 'TKT-'.$year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cashier_id' => User::factory(),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'itbis_amount' => $itbisAmount,
            'itbis_pct' => $itbisPct,
            'tip_amount' => $tipAmount,
            'total' => $total,
            'status' => 'paid',
            'ecf_requested' => false,
            'ecf_status' => 'na',
            'is_offline' => false,
        ];
    }

    /**
     * State for a voided ticket.
     */
    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => 'voided',
            'void_reason' => 'Test void reason for testing purposes.',
            'voided_at' => now(),
        ]);
    }

    /**
     * State for a paid ticket.
     */
    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid']);
    }
}
