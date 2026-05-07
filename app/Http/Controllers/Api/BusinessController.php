<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessDiscoveryResource;
use App\Http\Resources\BusinessPublicResource;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Service;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    /**
     * Search businesses by name.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $query = $request->input('q');

        $businesses = Business::where('status', 'active')
            ->where('name', 'LIKE', "%{$query}%")
            ->withCount(['services', 'employees'])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return BusinessResource::collection($businesses);
    }

    /**
     * Discover businesses with optional filters: text search, sector, province, geo radius, service.
     *
     * Accepts both ?q= and ?search= for the text search parameter.
     * ?q= takes precedence when both are present (legacy compatibility).
     * Mobile app sends ?search=; web/admin may use ?q=.
     */
    public function discover(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'sector' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'between:0.5,500'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'page' => ['nullable', 'integer'],
        ]);

        // Resolve text search term: ?q= takes precedence over ?search=
        // Both params are accepted; mobile app sends ?search=, web/admin may send ?q=
        $searchTerm = $validated['q'] ?? $validated['search'] ?? null;

        $query = Business::where('status', 'active')
            ->withCount(['services', 'employees']);

        if (! empty($searchTerm)) {
            // MATCH...AGAINST requires MySQL with a FULLTEXT index; fall back to LIKE
            // for non-MySQL environments (e.g. SQLite in tests).
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $query->whereRaw('MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchTerm]);
            } else {
                $query->where(function ($q) use ($searchTerm): void {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }
        }

        if (! empty($validated['sector'])) {
            $query->where('sector', $validated['sector']);
        }

        if (! empty($validated['province'])) {
            $query->where('province', $validated['province']);
        }

        if (! empty($validated['service_id'])) {
            $serviceId = (int) $validated['service_id'];
            $query->whereHas('services', fn ($q) => $q->where('services.id', $serviceId));
        }

        $lat = isset($validated['lat']) && $validated['lat'] !== null ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) && $validated['lng'] !== null ? (float) $validated['lng'] : null;

        if ($lat !== null && $lng !== null) {
            $radiusKm = isset($validated['radius_km']) && $validated['radius_km'] !== null
                ? (float) $validated['radius_km']
                : 25.0;

            // Bounding box pre-filter to leverage indexed lat/lng columns.
            $latDelta = $radiusKm / 111.32;
            $lngDelta = $radiusKm / (111.32 * cos(deg2rad($lat)));

            $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);

            // Select precise great-circle distance using ST_Distance_Sphere.
            $query->selectRaw(
                'businesses.*, ST_Distance_Sphere(location, ST_GeomFromText(CONCAT(\'POINT(\', ?, \' \', ?, \')\'), 4326)) / 1000 as distance_km',
                [$lng, $lat]
            )->orderBy('distance_km', 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        $businesses = $query->paginate(min($request->integer('per_page', 15), 50));

        return BusinessDiscoveryResource::collection($businesses);
    }

    /**
     * Get full public profile of a business by numeric ID (route model binding).
     */
    public function show(Business $business): BusinessPublicResource
    {
        if ($business->status !== 'active') {
            abort(404);
        }

        return $this->buildPublicResource($business);
    }

    /**
     * Get full public profile of a business by slug.
     */
    public function showBySlug(Business $business): BusinessPublicResource
    {
        if ($business->status !== 'active') {
            abort(404);
        }

        return $this->buildPublicResource($business);
    }

    /**
     * Eager-load relations and counts, then return a BusinessPublicResource.
     * Shared by show() (numeric ID) and showBySlug().
     */
    private function buildPublicResource(Business $business): BusinessPublicResource
    {
        $business->load([
            'services' => fn ($q) => $q->where('is_active', true),
            'serviceCategories',
            'employees' => fn ($q) => $q->where('is_active', true)->with('user:id,name'),
        ]);

        $business->loadCount(['services', 'employees']);

        return new BusinessPublicResource($business);
    }

    /**
     * Get business details by invitation code.
     */
    public function showByInvitationCode(string $invitationCode): BusinessResource
    {
        $business = Business::where('invitation_code', $invitationCode)
            ->where('status', 'active')
            ->withCount(['services', 'employees'])
            ->firstOrFail();

        return new BusinessResource($business);
    }

    /**
     * Get all active employees for a business.
     */
    public function employees(int $businessId): AnonymousResourceCollection
    {
        $business = Business::findOrFail($businessId);

        $employees = Employee::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with(['user:id,name', 'services:id,name,price,duration'])
            ->get();

        return EmployeeResource::collection($employees);
    }

    /**
     * Get employees who can provide a specific service within a business.
     */
    public function serviceEmployees(int $businessId, int $serviceId): AnonymousResourceCollection
    {
        $business = Business::findOrFail($businessId);

        $employees = Employee::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereHas('services', function ($query) use ($serviceId) {
                $query->where('services.id', $serviceId);
            })
            ->with(['user:id,name'])
            ->get();

        return EmployeeResource::collection($employees);
    }

    /**
     * Get available time slots for a business (public, no auth required).
     */
    public function availability(int $businessId, Request $request): JsonResponse
    {
        $business = Business::findOrFail($businessId);

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'employee_id' => 'nullable|exists:employees,id',
            'date' => 'required|date',
        ]);

        $serviceId = (int) $validated['service_id'];
        $date = $validated['date'];

        $service = Service::findOrFail($serviceId);

        // If employee_id provided, get slots for that specific employee
        if (! empty($validated['employee_id'])) {
            $employeeId = (int) $validated['employee_id'];

            $slots = $this->appointmentService->getAvailableSlots(
                $employeeId,
                $serviceId,
                $date
            );

            $formattedSlots = $slots->map(fn (string $start) => [
                'start' => $start,
                'end' => Carbon::parse($date.' '.$start)->addMinutes($service->duration)->format('H:i:s'),
            ]);

            return response()->json([
                'date' => $date,
                'slots' => $formattedSlots,
            ]);
        }

        // If no employee_id, aggregate slots from all employees who provide this service
        $employees = Employee::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereHas('services', function ($query) use ($serviceId) {
                $query->where('services.id', $serviceId);
            })
            ->with('user:id,name')
            ->get();

        $allSlots = collect();

        foreach ($employees as $employee) {
            $employeeSlots = $this->appointmentService->getAvailableSlots(
                $employee->id,
                $serviceId,
                $date
            );

            foreach ($employeeSlots as $slot) {
                $allSlots->push([
                    'start' => $slot,
                    'end' => Carbon::parse($date.' '.$slot)->addMinutes($service->duration)->format('H:i:s'),
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->user->name ?? null,
                ]);
            }
        }

        return response()->json([
            'date' => $date,
            'slots' => $allSlots->sortBy('start')->values(),
        ]);
    }
}
