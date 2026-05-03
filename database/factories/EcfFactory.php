<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Ecf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ecf>
 */
class EcfFactory extends Factory
{
    protected $model = Ecf::class;

    public function definition(): array
    {
        $montoGravado = fake()->randomFloat(2, 500, 100000);
        $itbis = round($montoGravado * 0.18, 2);
        $total = round($montoGravado + $itbis, 2);

        return [
            'business_id' => Business::factory(),
            'appointment_id' => null,
            'pos_ticket_id' => null,
            'numero_ecf' => 'B01'.fake()->numerify('##########'),
            'tipo' => '31',
            'rnc_comprador' => '1'.fake()->numerify('##########'),
            'razon_social_comprador' => fake()->company().' SRL',
            'nombre_comprador' => null,
            'fecha_emision' => now()->toDateString(),
            'monto_total' => $total,
            'itbis_total' => $itbis,
            'monto_gravado' => $montoGravado,
            'status' => 'draft',
            'track_id' => null,
            'xml_path' => null,
            'last_polled_at' => null,
            'error_message' => null,
        ];
    }

    public function tipo32(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => '32',
            'numero_ecf' => 'B02'.fake()->numerify('##########'),
            'rnc_comprador' => null,
            'razon_social_comprador' => null,
            'nombre_comprador' => fake()->name(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'track_id' => 'TRACK-'.fake()->uuid(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'track_id' => 'TRACK-'.fake()->uuid(),
        ]);
    }
}
