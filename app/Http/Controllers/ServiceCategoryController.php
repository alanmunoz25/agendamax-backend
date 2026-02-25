<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceCategoryRequest;
use App\Http\Requests\UpdateServiceCategoryRequest;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $categories = ServiceCategory::query()
            ->roots()
            ->with(['children' => fn ($q) => $q->withCount('services')])
            ->withCount(['services', 'children'])
            ->when(request('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
                ->orWhereHas('children', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            )
            ->when(request('is_active') !== null, fn ($query) => $query->where('is_active', request('is_active') === '1'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ServiceCategories/Index', [
            'categories' => $categories,
            'filters' => request()->only(['search', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', ServiceCategory::class);

        $parentCategories = ServiceCategory::query()
            ->roots()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('ServiceCategories/Create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceCategory::class);

        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        ServiceCategory::create([
            ...$data,
            'business_id' => auth()->user()->business_id,
        ]);

        return redirect()->route('service-categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ServiceCategory $serviceCategory): Response
    {
        $this->authorize('update', $serviceCategory);

        $serviceCategory->load('parent');

        // Get root categories excluding self and own children for parent dropdown
        $parentCategories = ServiceCategory::query()
            ->roots()
            ->where('is_active', true)
            ->where('id', '!=', $serviceCategory->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('ServiceCategories/Edit', [
            'category' => $serviceCategory,
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $this->authorize('update', $serviceCategory);

        $data = $request->validated();

        // Regenerate slug if name changed
        if (isset($data['name']) && $data['name'] !== $serviceCategory->name) {
            $data['slug'] = Str::slug($data['name']);
        }

        $serviceCategory->update($data);

        return redirect()->route('service-categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        $this->authorize('delete', $serviceCategory);

        $servicesCount = $serviceCategory->services()->count();
        $childrenServicesCount = $serviceCategory->children()
            ->withCount('services')
            ->get()
            ->sum('services_count');
        $totalServices = $servicesCount + $childrenServicesCount;

        if ($totalServices > 0) {
            return back()->withErrors([
                'delete' => "Cannot delete this category. It has {$totalServices} service(s) assigned. Please reassign or remove them first.",
            ]);
        }

        // Delete children first, then parent
        $serviceCategory->children()->delete();
        $serviceCategory->delete();

        return redirect()->route('service-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
