<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BusinessFeConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent command that re-encrypts any business_fe_configs rows that still
 * store certificado_digital as plain base64 (before the 'encrypted' cast was added).
 *
 * Idempotence guarantee: rows with a non-null cert_encrypted_at timestamp are
 * skipped unconditionally, so running the command twice is safe.
 *
 * Usage: php artisan fe:encrypt-existing-certificates
 */
class FEEncryptExistingCertificates extends Command
{
    /** @var string */
    protected $signature = 'fe:encrypt-existing-certificates';

    /** @var string */
    protected $description = 'Re-encrypts plain-base64 certificado_digital values using the Laravel encrypted cast (idempotent).';

    public function handle(): int
    {
        // Only process rows that have a certificate but have not yet been encrypted
        // by this command (cert_encrypted_at IS NULL acts as the idempotence flag).
        $pending = DB::table('business_fe_configs')
            ->whereNotNull('certificado_digital')
            ->whereNull('cert_encrypted_at')
            ->get(['id', 'certificado_digital']);

        if ($pending->isEmpty()) {
            $this->info('No hay certificados pendientes de cifrado.');

            return self::SUCCESS;
        }

        $this->info("Procesando {$pending->count()} registro(s)...");

        $migrated = 0;
        $skipped = 0;

        foreach ($pending as $row) {
            $rawValue = $row->certificado_digital;

            if (empty($rawValue)) {
                $skipped++;

                continue;
            }

            // We cannot use withoutGlobalScopes()->find() here because Eloquent would
            // attempt to decrypt the plain-base64 value via the 'encrypted' cast and
            // throw a DecryptException. Instead, we use a fresh model instance and
            // forceFill only the necessary attributes, then save by primary key.
            $config = new BusinessFeConfig;
            $config->exists = true;
            $config->forceFill(['id' => $row->id]);

            // Assign the plain value; the 'encrypted' cast encrypts it on save().
            $config->certificado_digital = $rawValue;
            $config->forceFill(['cert_encrypted_at' => now()])->save();

            $migrated++;
        }

        $this->info("Migración completada: {$migrated} cifrado(s), {$skipped} omitido(s).");

        return self::SUCCESS;
    }
}
