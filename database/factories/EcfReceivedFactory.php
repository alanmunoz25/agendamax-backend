<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\EcfReceived;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EcfReceived>
 */
class EcfReceivedFactory extends Factory
{
    protected $model = EcfReceived::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monto = fake()->randomFloat(2, 500, 100000);
        $itbis = round($monto * 0.18, 2);

        return [
            'business_id' => Business::factory(),
            'rnc_emisor' => '1'.fake()->numerify('##########'),
            'razon_social_emisor' => fake()->company().' SRL',
            'nombre_comercial_emisor' => fake()->company(),
            'correo_emisor' => fake()->companyEmail(),
            'numero_ecf' => 'B01'.fake()->numerify('##########'),
            'tipo' => '31',
            'fecha_emision' => now()->toDateString(),
            'monto_total' => $monto + $itbis,
            'itbis_total' => $itbis,
            'xml_path' => null,
            'xml_arecf_path' => null,
            'status' => 'pending',
            'codigo_motivo' => null,
            'arecf_sent_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'arecf_sent_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'codigo_motivo' => '1',
            'arecf_sent_at' => now(),
        ]);
    }
}
