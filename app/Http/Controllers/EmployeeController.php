<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of employees.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->with(['user', 'services'])
            ->when(request('search'), fn ($query, $search) => $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(request('is_active') !== null, fn ($query) => $query->where('is_active', request('is_active') === '1'))
            ->orderBy(request('sort', 'created_at'), request('direction', 'desc'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => request()->only(['search', 'is_active', 'sort', 'direction']),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        // Get users in current business without employee profiles
        $availableUsers = User::query()
            ->where('business_id', auth()->user()->primary_business_id)
            ->whereDoesntHave('employee')
            ->get(['id', 'name', 'email', 'role']);

        $services = Service::query()
            ->where('business_id', auth()->user()->primary_business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        return Inertia::render('Employees/Create', [
            'availableUsers' => $availableUsers,
            'services' => $services,
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = Employee::create([
            ...$request->safe()->except('service_ids'),
            'business_id' => auth()->user()->primary_business_id,
        ]);

        // Attach services if provided
        if ($request->has('service_ids')) {
            $employee->services()->sync($request->input('service_ids'));
        }

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load(['user', 'services.serviceCategory', 'schedules']);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
            'schedules' => $employee->schedules,
        ]);
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        $employee->load(['user', 'services']);

        $services = Service::query()
            ->where('business_id', auth()->user()->primary_business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'services' => $services,
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $employee->update($request->safe()->except('service_ids'));

        // Sync services if provided
        if ($request->has('service_ids')) {
            $employee->services()->sync($request->input('service_ids'));
        }

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }
}
