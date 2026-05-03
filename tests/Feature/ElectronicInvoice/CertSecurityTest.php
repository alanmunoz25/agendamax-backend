<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Services\ElectronicInvoice\CertificateConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

class CertSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that CertificateConversionService uses -passin file: instead of pass:$password.
     * This prevents the certificate password from appearing in process lists (ps aux).
     */
    public function test_certificate_password_not_passed_via_command_line_argument(): void
    {
        $reflection = new ReflectionClass(CertificateConversionService::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertNotFalse($source);

        // Assert the dangerous pattern is NOT present
        $dangerousPattern = 'pass:{$escapedPassword}';
        $this->assertStringNotContainsString(
            $dangerousPattern,
            $source,
            'CertificateConversionService must not pass passwords as -pass:$var arguments (visible in ps aux).'
        );

        // Assert the safe pattern IS present
        $this->assertStringContainsString(
            '-passin file:',
            $source,
            'CertificateConversionService must use -passin file:<path> to protect the password from ps aux.'
        );

        $this->assertStringContainsString(
            'writePasswordFile',
            $source,
            'CertificateConversionService must use writePasswordFile() helper for password temp files.'
        );
    }

    /**
     * Verify that the writePasswordFile creates a file with restrictive permissions (0600).
     */
    public function test_password_temp_file_has_restrictive_permissions(): void
    {
        $reflection = new ReflectionClass(CertificateConversionService::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertNotFalse($source);
        $this->assertStringContainsString('chmod($path, 0600)', $source);
    }

    /**
     * Verify that certificado_digital is stored encrypted in the database.
     * The raw database value must NOT be the plain base64 value.
     */
    public function test_certificate_is_stored_encrypted(): void
    {
        $business = Business::factory()->create();

        $plainBase64 = base64_encode('fake-p12-bytes-for-test');

        // Create the config and assign the cert via the model (encrypted cast applies)
        $config = BusinessFeConfig::factory()->create(['business_id' => $business->id]);
        $config->certificado_digital = $plainBase64;
        $config->save();

        // Read raw from database — must NOT be the plain base64 value
        $rawValue = DB::table('business_fe_configs')
            ->where('id', $config->id)
            ->value('certificado_digital');

        $this->assertNotEquals(
            $plainBase64,
            $rawValue,
            'certificado_digital must be stored encrypted, not as plain base64.'
        );

        // But reading via Eloquent should return the decrypted value
        $config->refresh();
        $this->assertEquals($plainBase64, $config->certificado_digital);
    }

    /**
     * Verify that the EncryptExistingCertificates command migrates plain values and is idempotent.
     */
    public function test_existing_plain_certs_are_migrated_by_command(): void
    {
        $business = Business::factory()->create();
        $plainBase64 = base64_encode('fake-p12-for-migration-test');

        // Insert a plain-base64 value directly (bypassing the encrypted cast)
        DB::table('business_fe_configs')->insert([
            'business_id' => $business->id,
            'rnc_emisor' => '101000001',
            'razon_social' => 'Test Business SRL',
            'nombre_comercial' => 'Test',
            'ambiente' => 'TestECF',
            'activo' => false,
            'certificado_digital' => $plainBase64,
            'certificado_convertido' => false,
            'cert_encrypted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recordId = DB::table('business_fe_configs')
            ->where('business_id', $business->id)
            ->value('id');

        // Run the command
        $this->artisan('fe:encrypt-existing-certificates')
            ->expectsOutput('Migración completada: 1 cifrado(s), 0 omitido(s).')
            ->assertExitCode(0);

        // Raw DB value should now be encrypted (not equal to plain base64)
        $rawAfter = DB::table('business_fe_configs')
            ->where('id', $recordId)
            ->value('certificado_digital');

        $this->assertNotEquals($plainBase64, $rawAfter, 'Plain cert should be encrypted after migration.');

        // cert_encrypted_at should be stamped
        $certEncryptedAt = DB::table('business_fe_configs')
            ->where('id', $recordId)
            ->value('cert_encrypted_at');

        $this->assertNotNull($certEncryptedAt, 'cert_encrypted_at should be set after migration.');

        // Run again — idempotence: no records should be processed
        $this->artisan('fe:encrypt-existing-certificates')
            ->expectsOutput('No hay certificados pendientes de cifrado.')
            ->assertExitCode(0);
    }
}
