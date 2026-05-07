<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\ServiceDetailResource;
use App\Http\Resources\ServiceResource;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    /**
     * List all active services for a business with optional filters.
     */
    public function index(Request $request, int $businessId): AnonymousResourceCollection
    {
        $business = Business::findOrFail($businessId);

        $query = Service::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with(['serviceCategory.parent'])
            ->withCount('employees');

        if ($request->filled('category_id')) {
            $query->where('service_category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $perPage = min((int) $request->input('per_page', 15), 50);

        return ServiceResource::collection(
            $query->orderBy('name')->paginate($perPage)
        );
    }

    /**
     * Show a single service with its employees.
     */
    public function show(int $businessId, int $serviceId): ServiceDetailResource
    {
        $business = Business::findOrFail($businessId);

        $service = Service::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with([
                'serviceCategory.parent',
                'employees' => function ($query) {
                    $query->where('is_active', true)->with('user:id,name');
                },
            ])
            ->withCount('employees')
            ->findOrFail($serviceId);

        return new ServiceDetailResource($service);
    }

    /**
     * Get employees who can provide a specific service.
     */
    public function employees(int $serviceId): JsonResponse
    {
        $service = Service::with('business')->findOrFail($serviceId);

        $employees = Employee::where('business_id', $service->business_id)
            ->where('is_active', true)
            ->whereHas('services', function ($query) use ($serviceId) {
                $query->where('services.id', $serviceId);
            })
            ->with(['user:id,name'])
            ->get();

        return EmployeeResource::collection($employees)->response();
    }
}
