<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Service::class);

        $services = Service::query()
            ->with(['business', 'serviceCategory.parent'])
            ->when(request('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            )
            ->when(request('category_id'), fn ($query, $categoryId) => $query->where('service_category_id', $categoryId)
            )
            ->when(request('parent_category_id'), fn ($query, $parentId) => $query->whereHas('serviceCategory', fn ($q) => $q->where('parent_id', $parentId))
            )
            ->when(request('is_active') !== null, fn ($query) => $query->where('is_active', request('is_active') === '1')
            )
            ->orderBy(request('sort', 'name'), request('direction', 'asc'))
            ->paginate(15)
            ->withQueryString();

        $serviceCategories = ServiceCategory::query()
            ->roots()
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Services/Index', [
            'services' => $services,
            'serviceCategories' => $serviceCategories,
            'filters' => request()->only(['search', 'category_id', 'parent_category_id', 'is_active', 'sort', 'direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Service::class);

        $serviceCategories = ServiceCategory::query()
            ->roots()
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Services/Create', [
            'serviceCategories' => $serviceCategories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $data = $request->validated();

        // Auto-populate legacy category string from parent category
        if (! empty($data['service_category_id'])) {
            $subcategory = ServiceCategory::with('parent')->find($data['service_category_id']);
            if ($subcategory?->parent) {
                $data['category'] = $subcategory->parent->name;
            }
        }

        $service = Service::create([
            ...$data,
            'business_id' => auth()->user()->business_id,
        ]);

        return redirect()->route('services.show', $service)
            ->with('success', 'Service created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service): Response
    {
        $this->authorize('view', $service);

        $service->load(['business', 'serviceCategory.parent']);

        return Inertia::render('Services/Show', [
            'service' => $service,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Service $service): Response
    {
        $this->authorize('update', $service);

        $service->load('serviceCategory.parent');

        $serviceCategories = ServiceCategory::query()
            ->roots()
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Services/Edit', [
            'service' => $service,
            'serviceCategories' => $serviceCategories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);

        $data = $request->validated();

        // Auto-populate legacy category string from parent category
        if (array_key_exists('service_category_id', $data) && ! empty($data['service_category_id'])) {
            $subcategory = ServiceCategory::with('parent')->find($data['service_category_id']);
            if ($subcategory?->parent) {
                $data['category'] = $subcategory->parent->name;
            }
        }

        $service->update($data);

        return redirect()->route('services.show', $service)
            ->with('success', 'Service updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return redirect()->route('services.index')
            ->with('success', 'Service deleted successfully.');
    }
}
