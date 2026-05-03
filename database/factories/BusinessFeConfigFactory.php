<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<BusinessFeConfig>
 */
class BusinessFeConfigFactory extends Factory
{
    protected $model = BusinessFeConfig::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'rnc_emisor' => '1'.fake()->numerify('##########'),
            'razon_social' => fake()->company().' SRL',
            'nombre_comercial' => fake()->company(),
            'direccion' => fake()->streetAddress(),
            'municipio' => '010100',
            'provincia' => '010000',
            'telefono' => '809-555-'.fake()->numerify('####'),
            'email' => fake()->companyEmail(),
            'actividad_economica' => '620100',
            'certificado_convertido' => false,
            'fecha_vigencia_cert' => null,
        ];
    }

    /**
     * Apply guarded fields via afterCreating since they are excluded from $fillable.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (BusinessFeConfig $config): void {
            $config->forceFill([
                'certificado_digital' => null,
                'password_certificado' => null,
                'ambiente' => 'TestECF',
                'activo' => false,
            ])->save();
        });
    }

    /**
     * Mark the config as active with an encrypted dummy password.
     */
    public function active(): static
    {
        return $this->afterCreating(function (BusinessFeConfig $config): void {
            $config->forceFill([
                'activo' => true,
                'password_certificado' => Crypt::encryptString('test_password'),
            ])->save();
        });
    }

    /**
     * State with a fully configured certificate (base64 P12 + password + converted flag).
     *
     * @param  string  $p12Base64  Base64-encoded P12 bytes
     * @param  string  $password  Plain-text password (will be encrypted)
     */
    public function withCertificate(string $p12Base64, string $password): static
    {
        return $this->afterCreating(function (BusinessFeConfig $config) use ($p12Base64, $password): void {
            $config->certificado_digital = $p12Base64; // encrypted cast applies
            $config->forceFill([
                'password_certificado' => Crypt::encryptString($password),
                'certificado_convertido' => true,
                'activo' => true,
            ])->save();
        });
    }
}
