<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Service;
use Database\Seeders\ServicePriceListSeeder;
use Illuminate\Console\Command;

class SeedServicesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'services:seed
                            {--business-id= : The business ID to import services for (defaults to 2)}
                            {--fresh : Delete existing services for the business before seeding}';

    /**
     * @var string
     */
    protected $description = 'Seed services from the CSV price list for a specific business';

    public function handle(): int
    {
        $businessId = $this->option('business-id')
            ? (int) $this->option('business-id')
            : null;

        $business = $businessId !== null ? Business::find($businessId) : null;

        if ($businessId !== null && ! $business) {
            $this->error("Business with ID {$businessId} not found.");

            return self::FAILURE;
        }

        if ($this->option('fresh') && $businessId !== null) {
            $count = Service::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->count();

            if ($count > 0 && ! $this->confirm("This will delete {$count} existing services for \"{$business->name}\". Continue?")) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }

            $deleted = Service::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->delete();

            $this->info("Deleted {$deleted} existing services.");
        }

        $seeder = new ServicePriceListSeeder;
        $seeder->setCommand($this);
        $seeder->run($businessId);

        return self::SUCCESS;
    }
}
