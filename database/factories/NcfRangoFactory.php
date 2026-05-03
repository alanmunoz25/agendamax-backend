<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\NcfRango;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NcfRango>
 */
class NcfRangoFactory extends Factory
{
    protected $model = NcfRango::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'tipo_ecf' => 31,
            'numero_solicitud' => null,
            'numero_autorizacion' => null,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 1,
            'fecha_vencimiento' => now()->addYears(2)->toDateString(),
            'status' => 'active',
        ];
    }

    /**
     * State for an exhausted range (all sequential numbers consumed).
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'proximo_secuencial' => $attributes['secuencia_hasta'] + 1,
            'status' => 'exhausted',
        ]);
    }

    /**
     * State for an expired range (authorization date has passed).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'status' => 'expired',
        ]);
    }

    /**
     * State for a range of a specific e-CF type.
     */
    public function forTipo(int $tipo): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_ecf' => $tipo,
        ]);
    }
}
