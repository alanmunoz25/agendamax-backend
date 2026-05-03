<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeadRequest;
use App\Http\Requests\Api\StoreLeadWithAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    /**
     * Create a lead (user with lead role and random password).
     * The lead can later use the forgot-password flow to activate their account.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $user = new User;
        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Str::random(32),
            'interested_service_id' => $request->validated('interested_service_id'),
            'notes' => $request->validated('notes'),
            'source' => $request->validated('source'),
        ]);
        $user->forceFill([
            'role' => 'lead',
            'business_id' => $request->validated('business_id'),
        ])->save();

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Create a lead and an appointment atomically.
     * Finds or creates the lead by email + business_id, then books the appointment.
     */
    public function storeWithAppointment(StoreLeadWithAppointmentRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $businessId = (int) $request->validated('business_id');

                // Find existing lead or create a new one
                $lead = User::where('email', $request->validated('email'))
                    ->where('business_id', $businessId)
                    ->first();

                if ($lead) {
                    // Update non-null fields on the existing lead
                    $updates = array_filter([
                        'name' => $request->validated('name'),
                        'phone' => $request->validated('phone'),
                        'interested_service_id' => $request->validated('interested_service_id'),
                        'notes' => $request->validated('notes'),
                        'source' => $request->validated('source'),
                    ], fn ($value) => $value !== null);

                    if ($updates) {
                        $lead->update($updates);
                    }
                } else {
                    $lead = new User;
                    $lead->fill([
                        'name' => $request->validated('name'),
                        'email' => $request->validated('email'),
                        'phone' => $request->validated('phone'),
                        'password' => Str::random(32),
                        'interested_service_id' => $request->validated('interested_service_id'),
                        'notes' => $request->validated('notes'),
                        'source' => $request->validated('source'),
                    ]);
                    $lead->forceFill([
                        'role' => 'lead',
                        'business_id' => $businessId,
                    ])->save();
                }

                $employeeId = $request->validated('employee_id');

                $appointment = $this->appointmentService->createAppointment([
                    'service_id' => (int) $request->validated('service_id'),
                    'employee_id' => $employeeId !== null ? (int) $employeeId : null,
                    'client_id' => $lead->id,
                    'scheduled_at' => $request->validated('scheduled_at'),
                    'notes' => $request->validated('appointment_notes'),
                ]);

                $appointment->load(['service', 'employee.user', 'business']);

                return response()->json([
                    'lead' => new UserResource($lead),
                    'appointment' => new AppointmentResource($appointment),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create lead with appointment', [
                'email' => $request->validated('email'),
                'business_id' => $request->validated('business_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create lead with appointment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
