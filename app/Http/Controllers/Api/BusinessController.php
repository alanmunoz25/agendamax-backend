<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
