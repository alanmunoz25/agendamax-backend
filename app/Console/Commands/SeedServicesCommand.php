<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use Database\Seeders\ServicePriceListSeeder;
use Illuminate\Console\Command;

class SeedServicesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'services:seed
                            {--business-id= : The business ID to import services for (defaults to 2)}';

    /**
     * @var string
     */
    protected $description = 'Seed services from the CSV price list for a specific business';

    public function handle(): int
    {
        $businessId = $this->option('business-id')
            ? (int) $this->option('business-id')
            : null;

        if ($businessId !== null && ! Business::find($businessId)) {
            $this->error("Business with ID {$businessId} not found.");

            return self::FAILURE;
        }

        $seeder = new ServicePriceListSeeder;
        $seeder->setCommand($this);
        $seeder->run($businessId);

        return self::SUCCESS;
    }
}
