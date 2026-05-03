<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\ElectronicInvoice\DgiiAuthService;
use Illuminate\Console\Command;

class EcfTestAuth extends Command
{
    /** @var string */
    protected $signature = 'ecf:test-auth {--business=1 : ID of the business to test auth for}';

    /** @var string */
    protected $description = 'Test DGII authentication for a business (TestECF environment)';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');

        $this->info("Testing DGII authentication for business #{$businessId}...");

        $business = Business::withoutGlobalScopes()->find($businessId);

        if ($business === null) {
            $this->error("Business #{$businessId} not found.");

            return self::FAILURE;
        }

        $feConfig = $business->feConfig()->withoutGlobalScopes()->first();

        if ($feConfig === null) {
            $this->error("No BusinessFeConfig found for business #{$businessId}. Create one first.");

            return self::FAILURE;
        }

        $this->line("  Business: {$business->name}");
        $this->line("  RNC Emisor: {$feConfig->rnc_emisor}");
        $this->line("  Ambiente: {$feConfig->ambiente}");
        $this->line('  Certificado convertido: '.($feConfig->hasCertificate() ? 'Sí' : 'No'));
        $this->line('');

        if (! $feConfig->isReadyToEmit()) {
            $this->warn('BusinessFeConfig is not ready to emit (activo=false, missing cert, or missing password).');
            $this->warn('Attempting authentication anyway...');
        }

        try {
            $service = new DgiiAuthService($business, $feConfig);
            $token = $service->getToken();

            $this->info('Authentication successful.');
            $this->line('  Token (first 20 chars): '.substr($token, 0, 20).'...');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Authentication failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
