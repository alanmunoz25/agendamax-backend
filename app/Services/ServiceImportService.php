<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceImportService
{
    /**
     * Import services from parsed JSON data for a given business.
     *
     * @param  array<string, mixed>  $data
     * @return array{categories_created: int, categories_updated: int, services_created: int, services_updated: int}
     *
     * @throws ValidationException
     */
    public function import(int $businessId, array $data): array
    {
        $this->validate($data);

        $stats = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'services_created' => 0,
            'services_updated' => 0,
        ];

        DB::transaction(function () use ($businessId, $data, &$stats) {
            $sortOrder = 0;

            foreach ($data['categories'] as $categoryData) {
                $sortOrder++;
                $this->processCategory($businessId, $categoryData, null, $sortOrder, $stats);
            }
        });

        return $stats;
    }

    /**
     * Validate the import data structure.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.name' => ['required', 'string', 'max:255'],
            'categories.*.description' => ['nullable', 'string'],
            'categories.*.subcategories' => ['nullable', 'array'],
            'categories.*.subcategories.*.name' => ['required', 'string', 'max:255'],
            'categories.*.subcategories.*.description' => ['nullable', 'string'],
            'categories.*.subcategories.*.services' => ['nullable', 'array'],
            'categories.*.subcategories.*.services.*.name' => ['required', 'string', 'max:255'],
            'categories.*.subcategories.*.services.*.description' => ['nullable', 'string'],
            'categories.*.subcategories.*.services.*.duration' => ['required', 'integer', 'min:1'],
            'categories.*.subcategories.*.services.*.price' => ['required', 'numeric', 'min:0'],
            'categories.*.services' => ['nullable', 'array'],
            'categories.*.services.*.name' => ['required', 'string', 'max:255'],
            'categories.*.services.*.description' => ['nullable', 'string'],
            'categories.*.services.*.duration' => ['required', 'integer', 'min:1'],
            'categories.*.services.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $validator->validate();
    }

    /**
     * Process a single category (root or subcategory) and its services.
     *
     * @param  array<string, mixed>  $categoryData
     * @param  array{categories_created: int, categories_updated: int, services_created: int, services_updated: int}  $stats
     */
    private function processCategory(int $businessId, array $categoryData, ?int $parentId, int $sortOrder, array &$stats, ?string $rootCategoryName = null): void
    {
        $category = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('parent_id', $parentId)
            ->where('name', $categoryData['name'])
            ->first();

        $categoryAttributes = [
            'description' => $categoryData['description'] ?? null,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];

        if ($category) {
            $category->update($categoryAttributes);
            $stats['categories_updated']++;
        } else {
            $category = ServiceCategory::withoutGlobalScopes()->create([
                'business_id' => $businessId,
                'parent_id' => $parentId,
                'name' => $categoryData['name'],
                ...$categoryAttributes,
            ]);
            $stats['categories_created']++;
        }

        $effectiveRootName = $rootCategoryName ?? $categoryData['name'];

        // Process direct services under this category
        if (! empty($categoryData['services'])) {
            foreach ($categoryData['services'] as $serviceData) {
                $this->processService($businessId, $serviceData, $category->id, $effectiveRootName, $stats);
            }
        }

        // Process subcategories
        if (! empty($categoryData['subcategories'])) {
            $subSortOrder = 0;
            foreach ($categoryData['subcategories'] as $subcategoryData) {
                $subSortOrder++;
                $this->processCategory($businessId, $subcategoryData, $category->id, $subSortOrder, $stats, $effectiveRootName);
            }
        }
    }

    /**
     * Process a single service within a category.
     *
     * @param  array<string, mixed>  $serviceData
     * @param  array{categories_created: int, categories_updated: int, services_created: int, services_updated: int}  $stats
     */
    private function processService(int $businessId, array $serviceData, int $categoryId, string $rootCategoryName, array &$stats): void
    {
        $service = Service::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('name', $serviceData['name'])
            ->first();

        $serviceAttributes = [
            'description' => $serviceData['description'] ?? null,
            'duration' => $serviceData['duration'],
            'price' => $serviceData['price'],
            'service_category_id' => $categoryId,
            'category' => $rootCategoryName,
            'is_active' => true,
        ];

        if ($service) {
            $service->update($serviceAttributes);
            $stats['services_updated']++;
        } else {
            Service::withoutGlobalScopes()->create([
                'business_id' => $businessId,
                'name' => $serviceData['name'],
                ...$serviceAttributes,
            ]);
            $stats['services_created']++;
        }
    }
}
