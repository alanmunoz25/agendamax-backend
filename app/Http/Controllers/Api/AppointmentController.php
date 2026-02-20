<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    /**
     * Display a listing of the authenticated user's appointments.
     */
    public function index(): AnonymousResourceCollection
    {
        $appointments = Appointment::where('client_id', Auth::id())
            ->with(['service', 'employee.user', 'business'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(20);

        return AppointmentResource::collection($appointments);
    }

    /**
     * Store a newly created appointment.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        try {
            $appointment = $this->appointmentService->createAppointment([
                ...$request->validated(),
                'client_id' => Auth::id(),
            ]);

            $appointment->load(['service', 'employee.user', 'business']);

            return response()->json([
                'message' => 'Appointment created successfully',
                'appointment' => new AppointmentResource($appointment),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create appointment', [
                'user_id' => Auth::id(),
                'service_id' => $request->validated('service_id'),
                'employee_id' => $request->validated('employee_id'),
                'scheduled_at' => $request->validated('scheduled_at'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create appointment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified appointment.
     */
    public function show(int $id): AppointmentResource|JsonResponse
    {
        $appointment = Appointment::with(['service', 'employee.user', 'business', 'visit'])
            ->findOrFail($id);

        if ($appointment->client_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return new AppointmentResource($appointment);
    }

    /**
     * Cancel an appointment.
     */
    public function destroy(int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        if ($appointment->client_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $this->appointmentService->cancelAppointment(
                $id,
                'Cancelled by client'
            );

            return response()->json([
                'message' => 'Appointment cancelled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel appointment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available time slots for a service and employee.
     */
    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today',
        ]);

        $slots = $this->appointmentService->getAvailableSlots(
            (int) $validated['employee_id'],
            (int) $validated['service_id'],
            $validated['date']
        );

        return response()->json([
            'date' => $validated['date'],
            'slots' => $slots,
        ]);
    }
}
