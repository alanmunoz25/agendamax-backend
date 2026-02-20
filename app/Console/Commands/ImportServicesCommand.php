<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\ServiceImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'services:import
                            {file : Path to the JSON file to import}
                            {--business-id= : The business ID to import services for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import services and categories from a JSON file for a business';

    /**
     * Execute the console command.
     */
    public function handle(ServiceImportService $importService): int
    {
        $filePath = $this->argument('file');
        $businessId = $this->option('business-id');

        if (! $businessId) {
            $this->error('The --business-id option is required.');

            return self::FAILURE;
        }

        $businessId = (int) $businessId;

        if (! Business::find($businessId)) {
            $this->error("Business with ID {$businessId} not found.");

            return self::FAILURE;
        }

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        try {
            $stats = $importService->import($businessId, $data);
        } catch (ValidationException $e) {
            $this->error('Validation failed:');
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->line("  - {$field}: {$message}");
                }
            }

            return self::FAILURE;
        }

        $this->info('Import completed successfully!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Categories Created', $stats['categories_created']],
                ['Categories Updated', $stats['categories_updated']],
                ['Services Created', $stats['services_created']],
                ['Services Updated', $stats['services_updated']],
            ]
        );

        return self::SUCCESS;
    }
}
