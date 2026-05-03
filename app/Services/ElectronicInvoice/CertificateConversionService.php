<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

/**
 * Converts a DGII-issued legacy P12 certificate to a format compatible with xmlseclibs.
 *
 * DGII certificates commonly use legacy key derivation algorithms (RC2, 3DES) that modern
 * OpenSSL builds require the -legacy flag to read. This service performs a two-step conversion:
 *   Step 1: P12 (legacy) → PEM  [openssl pkcs12 -legacy]
 *   Step 2: PEM → new P12 (AES-256-CBC)  [openssl pkcs12 -export -certpbe AES-256-CBC -keypbe AES-256-CBC]
 *
 * The result is stored as base64 in the database, never on disk.
 */
class CertificateConversionService
{
    /**
     * Converts a legacy P12 file to a modern AES-256-CBC P12 and returns it base64-encoded.
     *
     * @param  string  $p12FilePath  Absolute path to the uploaded .p12 file
     * @param  string  $password  Password for the P12 (also used as password for the output P12)
     *
     * @throws \RuntimeException when any conversion step fails
     */
    public function convertAndEncode(string $p12FilePath, string $password): string
    {
        $tmpDir = sys_get_temp_dir();
        $uniqueId = uniqid('fe_cert_', true);

        $pemPath = $tmpDir.'/'.$uniqueId.'.pem';
        $newP12Path = $tmpDir.'/'.$uniqueId.'_converted.p12';

        try {
            $this->p12ToPem($p12FilePath, $pemPath, $password);
            $this->pemToP12($pemPath, $newP12Path, $password);

            $p12Bytes = file_get_contents($newP12Path);

            if ($p12Bytes === false || $p12Bytes === '') {
                throw new \RuntimeException('El archivo P12 convertido está vacío o no se pudo leer.');
            }

            return base64_encode($p12Bytes);
        } finally {
            if (file_exists($pemPath)) {
                @unlink($pemPath);
            }

            if (file_exists($newP12Path)) {
                @unlink($newP12Path);
            }
        }
    }

    /**
     * Step 1: Extracts PEM (private key + certificate chain) from a legacy P12.
     *
     * Uses the -legacy flag to handle certificates with RC2/3DES key derivation.
     * Password is passed via a temporary file to prevent exposure in ps aux.
     *
     * @throws \RuntimeException
     */
    private function p12ToPem(string $p12Path, string $pemPath, string $password): void
    {
        $passFile = $this->writePasswordFile($password);

        try {
            $escapedP12 = escapeshellarg($p12Path);
            $escapedPem = escapeshellarg($pemPath);
            $escapedPassFile = escapeshellarg($passFile);

            $command = "openssl pkcs12 -legacy -in {$escapedP12} -out {$escapedPem} -passin file:{$escapedPassFile} -passout file:{$escapedPassFile} -nodes 2>&1";

            $this->runCommand($command, 'P12 → PEM (legacy)');
        } finally {
            @unlink($passFile);
        }

        if (! file_exists($pemPath) || filesize($pemPath) === 0) {
            throw new \RuntimeException('El paso P12 → PEM no generó salida. Verifique la contraseña y el archivo.');
        }
    }

    /**
     * Step 2: Re-packages a PEM into a modern P12 using AES-256-CBC encryption.
     *
     * Password is passed via a temporary file to prevent exposure in ps aux.
     *
     * @throws \RuntimeException
     */
    private function pemToP12(string $pemPath, string $newP12Path, string $password): void
    {
        $passFile = $this->writePasswordFile($password);

        try {
            $escapedPem = escapeshellarg($pemPath);
            $escapedNewP12 = escapeshellarg($newP12Path);
            $escapedPassFile = escapeshellarg($passFile);

            $command = "openssl pkcs12 -export -in {$escapedPem} -out {$escapedNewP12} -passin file:{$escapedPassFile} -passout file:{$escapedPassFile} -certpbe AES-256-CBC -keypbe AES-256-CBC -iter 2048 2>&1";

            $this->runCommand($command, 'PEM → P12 (AES-256-CBC)');
        } finally {
            @unlink($passFile);
        }

        if (! file_exists($newP12Path) || filesize($newP12Path) === 0) {
            throw new \RuntimeException('El paso PEM → P12 no generó salida.');
        }
    }

    /**
     * Writes the password to a temp file with restricted permissions (0600).
     * The caller is responsible for unlinking the file after use.
     *
     * @throws \RuntimeException when the temp file cannot be created or written
     */
    private function writePasswordFile(string $password): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fe_pass_');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal para la contraseña del certificado.');
        }

        if (file_put_contents($path, $password) === false) {
            @unlink($path);
            throw new \RuntimeException('No se pudo escribir la contraseña en el archivo temporal.');
        }

        chmod($path, 0600);

        return $path;
    }

    /**
     * Runs a shell command via proc_open, capturing stderr separately.
     *
     * @throws \RuntimeException when the command exits with a non-zero status
     */
    private function runCommand(string $command, string $stepLabel): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new \RuntimeException("No se pudo iniciar el proceso OpenSSL para el paso: {$stepLabel}");
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim($stderr ?: $stdout ?: 'Sin detalle');
            throw new \RuntimeException(
                "Fallo en el paso OpenSSL [{$stepLabel}] (exit code {$exitCode}): {$detail}"
            );
        }
    }
}
