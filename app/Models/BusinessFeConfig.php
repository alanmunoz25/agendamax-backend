<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class BusinessFeConfig extends Model
{
    use BelongsToBusiness, HasFactory;

    /** @var string */
    protected $table = 'business_fe_configs';

    /** @var array<int, string> */
    protected $fillable = [
        'business_id',
        'rnc_emisor',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'municipio',
        'provincia',
        'telefono',
        'email',
        'actividad_economica',
        'certificado_convertido',
        'fecha_vigencia_cert',
        // 'certificado_digital' — excluded: must be written via service, not mass assignment
        // 'password_certificado' — excluded: must be written via service, not mass assignment
        // 'ambiente' — excluded: Test→Prod change must be explicit via service/policy
        // 'activo' — excluded: toggled via service, not mass assignment
    ];

    /** @var array<int, string> */
    protected $hidden = ['password_certificado', 'certificado_digital'];

    /**
     * Get the attributes that should be cast.
     *
     * - certificado_digital: stored encrypted via Laravel's built-in 'encrypted' cast.
     *   Any plain-base64 values from before this cast was applied must be re-encrypted
     *   using the FE\EncryptExistingCertificates artisan command.
     * - password_certificado: encrypted at application layer via Crypt::encryptString()
     *   in SettingsController; decoded via getDecryptedCertPassword().
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'certificado_convertido' => 'boolean',
            'fecha_vigencia_cert' => 'date',
            'certificado_digital' => 'encrypted',
        ];
    }

    /**
     * Returns true when the certificate is uploaded and converted.
     */
    public function hasCertificate(): bool
    {
        return ! empty($this->certificado_digital) && $this->certificado_convertido;
    }

    /**
     * Returns the raw bytes of the converted P12 certificate.
     *
     * @throws \RuntimeException when no certificate is stored
     */
    public function getCertificateP12(): string
    {
        if (! $this->hasCertificate()) {
            throw new \RuntimeException(
                "No hay certificado digital convertido para business {$this->business_id}."
            );
        }

        return base64_decode($this->certificado_digital, true) ?: throw new \RuntimeException(
            "Error decodificando certificado digital para business {$this->business_id}."
        );
    }

    /**
     * Decrypts and returns the certificate password.
     * Returns null if no password is stored.
     */
    public function getDecryptedCertPassword(): ?string
    {
        if (empty($this->password_certificado)) {
            return null;
        }

        return Crypt::decryptString($this->password_certificado);
    }

    /**
     * Validates that the configuration is complete enough to emit e-CFs.
     */
    public function isReadyToEmit(): bool
    {
        return $this->activo
            && ! empty($this->rnc_emisor)
            && ! empty($this->razon_social)
            && $this->hasCertificate()
            && ! empty($this->password_certificado);
    }

    /**
     * Get the ECFs (emitted) for this business FE config.
     */
    public function ecfs(): HasMany
    {
        return $this->hasMany(Ecf::class, 'business_id', 'business_id');
    }

    /**
     * Get the NCF ranges (rangos) for this business.
     */
    public function ncfRangos(): HasMany
    {
        return $this->hasMany(NcfRango::class, 'business_id', 'business_id');
    }
}
