<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\CommissionRule;
use App\Models\Employee;
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
            'business_id' => auth()->user()->primary_business_id,
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

        $commissionRules = CommissionRule::query()
            ->where('service_id', $service->id)
            ->with('employee.user')
            ->orderBy('is_active', 'desc')
            ->get()
            ->map(fn ($rule) => [
                'id' => $rule->id,
                'scope_type' => $this->deriveCommissionScopeType($rule),
                'type' => $rule->type,
                'value' => $rule->value,
                'is_active' => $rule->is_active,
                'employee' => $rule->employee ? [
                    'id' => $rule->employee->id,
                    'name' => $rule->employee->user?->name,
                ] : null,
                'effective_from' => $rule->effective_from?->format('Y-m-d'),
                'effective_until' => $rule->effective_until?->format('Y-m-d'),
            ]);

        $globalRuleCount = CommissionRule::query()
            ->where('business_id', $service->business_id)
            ->whereNull('employee_id')
            ->whereNull('service_id')
            ->where('is_active', true)
            ->count();

        $allEmployees = Employee::query()
            ->where('is_active', true)
            ->with('user:id,name')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->user?->name,
            ]);

        return Inertia::render('Services/Show', [
            'service' => $service,
            'commission_rules' => $commissionRules,
            'global_rule_count' => $globalRuleCount,
            'all_employees' => $allEmployees,
        ]);
    }

    /**
     * Derive the scope_type string from the commission rule's nullable FK columns.
     * The DB does not store scope_type directly — it is represented by employee_id / service_id presence.
     */
    private function deriveCommissionScopeType(CommissionRule $rule): string
    {
        if ($rule->employee_id !== null && $rule->service_id !== null) {
            return 'specific';
        }

        if ($rule->employee_id === null && $rule->service_id !== null) {
            return 'per_service';
        }

        if ($rule->employee_id !== null && $rule->service_id === null) {
            return 'per_employee';
        }

        return 'global';
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
