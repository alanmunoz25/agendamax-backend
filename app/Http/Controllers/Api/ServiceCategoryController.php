<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\Business;
use App\Models\ServiceCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceCategoryController extends Controller
{
    /**
     * List all root categories for a business with their children, services, and service counts.
     */
    public function index(int $businessId): AnonymousResourceCollection
    {
        $business = Business::findOrFail($businessId);

        $categories = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($query) {
                $query->where('is_active', true)
                    ->with(['services' => function ($query) {
                        $query->where('is_active', true)
                            ->orderBy('name');
                    }])
                    ->withCount(['services' => function ($query) {
                        $query->where('is_active', true);
                    }])
                    ->orderBy('sort_order');
            }])
            ->with(['services' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name');
            }])
            ->withCount(['services' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }

    /**
     * Show a single category with its services.
     */
    public function show(int $businessId, int $categoryId): ServiceCategoryResource
    {
        $business = Business::findOrFail($businessId);

        $category = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with([
                'children' => function ($query) {
                    $query->where('is_active', true)
                        ->withCount(['services' => function ($query) {
                            $query->where('is_active', true);
                        }])
                        ->orderBy('sort_order');
                },
                'services' => function ($query) {
                    $query->where('is_active', true)
                        ->with(['serviceCategory.parent'])
                        ->withCount('employees')
                        ->orderBy('name');
                },
            ])
            ->withCount(['services' => function ($query) {
                $query->where('is_active', true);
            }])
            ->findOrFail($categoryId);

        return new ServiceCategoryResource($category);
    }
}
