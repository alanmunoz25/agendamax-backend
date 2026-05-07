<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicePriceListSeeder extends Seeder
{
    /**
     * The default business ID to import services for (used when no argument is provided).
     */
    private const DEFAULT_BUSINESS_ID = 2;

    /**
     * Default service duration in minutes (CSV does not include duration).
     */
    private const DEFAULT_DURATION = 30;

    /**
     * Character replacements to fix Mac Roman encoding issues in the CSV.
     *
     * @var array<string, string>
     */
    private array $encodingFixes = [
        "\xC3\xB1" => 'ñ', // already correct UTF-8 ñ (pass-through)
        '–' => 'ñ',        // en dash → ñ
        '—' => 'ó',        // em dash → ó
        'Ž' => 'é',        // Ž → é
        '‡' => 'á',        // ‡ → á
    ];

    /**
     * Import statistics.
     *
     * @var array{categories_created: int, categories_updated: int, services_created: int, services_updated: int, skipped: int}
     */
    private array $stats = [
        'categories_created' => 0,
        'categories_updated' => 0,
        'services_created' => 0,
        'services_updated' => 0,
        'skipped' => 0,
    ];

    /**
     * Run the database seeds.
     *
     * Usage:
     *   php artisan db:seed --class=ServicePriceListSeeder                    # Uses default business ID (2)
     *   php artisan db:seed --class=ServicePriceListSeeder -- --business-id=4 # Imports to business 4
     */
    public function run(?int $businessId = null): void
    {
        $businessId = $businessId ?? self::DEFAULT_BUSINESS_ID;

        $business = Business::find($businessId);

        if (! $business) {
            $this->command->error("Business ID {$businessId} not found. Run DatabaseSeeder first.");

            return;
        }

        $this->command->info("Importing price list for: {$business->name} (ID: {$business->id})");
        $this->command->newLine();

        $csvPath = database_path('seeders/data/price-structure-services-v1.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");

            return;
        }

        $rows = $this->parseCsv($csvPath);

        $this->command->info('Parsed '.count($rows).' valid service rows from CSV.');

        DB::transaction(function () use ($rows, $business) {
            $categorySort = 0;

            // Group rows by Category → Sub_Category
            $grouped = $this->groupByCategories($rows);

            foreach ($grouped as $categoryName => $subcategories) {
                $parentCategory = $this->upsertCategory(
                    $business->id,
                    null,
                    $categoryName,
                    $categorySort++
                );

                $subSort = 0;
                foreach ($subcategories as $subcategoryName => $services) {
                    $childCategory = $this->upsertCategory(
                        $business->id,
                        $parentCategory->id,
                        $subcategoryName,
                        $subSort++
                    );

                    foreach ($services as $serviceData) {
                        $this->upsertService($business->id, $childCategory, $parentCategory, $serviceData);
                    }
                }
            }
        });

        $this->command->newLine();
        $this->command->info('Import completed!');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Categories Created', $this->stats['categories_created']],
                ['Categories Updated', $this->stats['categories_updated']],
                ['Services Created', $this->stats['services_created']],
                ['Services Updated', $this->stats['services_updated']],
                ['Rows Skipped', $this->stats['skipped']],
            ]
        );
    }

    /**
     * Parse the CSV file and return cleaned rows.
     *
     * CSV columns: Category, Sub_Category, Category_description, Service_Description, Price
     *
     * @return array<int, array{category: string, subcategory: string, description: string, name: string, price: float}>
     */
    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        // Skip header row
        fgetcsv($handle);

        $lineNumber = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // Skip rows with insufficient columns (need all 5)
            if (count($data) < 5) {
                $this->stats['skipped']++;

                continue;
            }

            $category = $this->fixEncoding(trim($data[0]));
            $subcategory = $this->fixEncoding(trim($data[1]));
            $description = $this->fixEncoding(trim($data[2]));
            $serviceName = $this->fixEncoding(trim($data[3]));
            $priceRaw = trim($data[4]);

            // Skip empty service names
            if ($serviceName === '') {
                $this->stats['skipped']++;

                continue;
            }

            // Skip rows without a price
            if ($priceRaw === '') {
                $this->command->warn("  Skipped line {$lineNumber}: \"{$serviceName}\" (no price)");
                $this->stats['skipped']++;

                continue;
            }

            // Parse price: remove commas and whitespace, convert to float
            $price = (float) str_replace([',', ' '], '', $priceRaw);

            $rows[] = [
                'category' => $category,
                'subcategory' => $subcategory,
                'description' => $description,
                'name' => $serviceName,
                'price' => $price,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Group flat rows into Category → Sub_Category → Services hierarchy.
     *
     * @param  array<int, array{category: string, subcategory: string, description: string, name: string, price: float}>  $rows
     * @return array<string, array<string, array<int, array{description: string, name: string, price: float}>>>
     */
    private function groupByCategories(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['category']][$row['subcategory']][] = [
                'description' => $row['description'],
                'name' => $row['name'],
                'price' => $row['price'],
            ];
        }

        return $grouped;
    }

    /**
     * Create or update a service category.
     */
    private function upsertCategory(int $businessId, ?int $parentId, string $name, int $sortOrder): ServiceCategory
    {
        $category = ServiceCategory::withoutGlobalScopes()->updateOrCreate(
            [
                'business_id' => $businessId,
                'parent_id' => $parentId,
                'name' => $name,
            ],
            [
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]
        );

        if ($category->wasRecentlyCreated) {
            $this->stats['categories_created']++;
            $this->command->line("  + Category: {$name}");
        } else {
            $this->stats['categories_updated']++;
        }

        return $category;
    }

    /**
     * Create or update a service.
     *
     * @param  array{description: string, name: string, price: float}  $data
     */
    private function upsertService(int $businessId, ServiceCategory $subcategory, ServiceCategory $parentCategory, array $data): void
    {
        $service = Service::withoutGlobalScopes()->updateOrCreate(
            [
                'business_id' => $businessId,
                'service_category_id' => $subcategory->id,
                'name' => $data['name'],
                'description' => $data['description'],
            ],
            [
                'price' => $data['price'],
                'duration' => self::DEFAULT_DURATION,
                'category' => $parentCategory->name,
                'is_active' => true,
            ]
        );

        if ($service->wasRecentlyCreated) {
            $this->stats['services_created']++;
        } else {
            $this->stats['services_updated']++;
        }
    }

    /**
     * Fix encoding issues from Mac Roman CSV export.
     */
    private function fixEncoding(string $text): string
    {
        return str_replace(
            array_keys($this->encodingFixes),
            array_values($this->encodingFixes),
            $text
        );
    }
}
