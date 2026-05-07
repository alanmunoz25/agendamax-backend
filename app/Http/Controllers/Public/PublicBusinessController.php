<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceCategory;
use Inertia\Inertia;
use Inertia\Response;

class PublicBusinessController extends Controller
{
    /**
     * Display the public business landing page.
     */
    public function show(Business $business): Response
    {
        if ($business->status !== 'active') {
            abort(404);
        }

        $services = Service::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with([
                'serviceCategory' => fn ($q) => $q->with('parent'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'price', 'duration', 'service_category_id']);

        $employees = Employee::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with('user:id,name')
            ->get()
            ->map(fn (Employee $emp) => [
                'id' => $emp->id,
                'name' => $emp->user?->name,
                'photo_url' => $emp->photo_url ?? null,
                'bio' => $emp->bio ?? null,
            ]);

        $categories = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name')->select(['id', 'name', 'parent_id'])])
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $publicBusiness = [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'description' => $business->description,
            'address' => $business->address,
            'logo_url' => $business->logo_url,
            'banner_url' => $business->banner_url,
            'cover_image_url' => $business->cover_image_url,
            'sector' => $business->sector ?? null,
            'province' => $business->province ?? null,
            'country' => $business->country ?? 'DO',
            'latitude' => $business->latitude ?? null,
            'longitude' => $business->longitude ?? null,
        ];

        return Inertia::render('Public/BusinessLanding', [
            'business' => $publicBusiness,
            'services' => $services,
            'employees' => $employees,
            'categories' => $categories,
        ]);
    }
}
