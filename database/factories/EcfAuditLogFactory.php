<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Ecf;
use App\Models\EcfAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EcfAuditLog>
 */
class EcfAuditLogFactory extends Factory
{
    protected $model = EcfAuditLog::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'ecf_id' => Ecf::factory(),
            'action' => fake()->randomElement(['sign', 'send', 'poll', 'cancel']),
            'payload' => null,
            'response' => null,
            'status_code' => 200,
            'error' => null,
            'duration_ms' => fake()->numberBetween(50, 3000),
        ];
    }
}
