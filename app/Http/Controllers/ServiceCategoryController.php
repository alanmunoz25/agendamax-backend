<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceCategoryRequest;
use App\Http\Requests\UpdateServiceCategoryRequest;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('service-categories', 'public');
        }

        unset($data['image']);

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
        $serviceCategory->append('image_url');

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

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($serviceCategory->image_path) {
                Storage::disk('public')->delete($serviceCategory->image_path);
            }

            $data['image_path'] = $request->file('image')->store('service-categories', 'public');
        }

        unset($data['image']);

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

        // Delete image from storage if exists
        if ($serviceCategory->image_path) {
            Storage::disk('public')->delete($serviceCategory->image_path);
        }

        // Delete children images and then children, then parent
        foreach ($serviceCategory->children as $child) {
            if ($child->image_path) {
                Storage::disk('public')->delete($child->image_path);
            }
        }

        $serviceCategory->children()->delete();
        $serviceCategory->delete();

        return redirect()->route('service-categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
