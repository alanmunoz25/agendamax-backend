<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Appointments\StoreAppointmentServiceRequest;
use App\Http\Requests\Appointments\UpdateAppointmentServiceRequest;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Pivots\AppointmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AppointmentServiceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Add a service line to an existing appointment.
     */
    public function store(StoreAppointmentServiceRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $employeeId = $request->validated('employee_id');
        $serviceId = $request->validated('service_id');

        /** @var Employee $employee */
        $employee = Employee::findOrFail($employeeId);

        // Multi-tenant defense: employee must belong to the same business as the appointment.
        if ($employee->business_id !== $appointment->business_id) {
            throw ValidationException::withMessages([
                'employee_id' => 'El colaborador no pertenece al negocio de esta cita.',
            ]);
        }

        // Business rule: employee must be able to provide the requested service.
        $providesService = $employee->services()->where('services.id', $serviceId)->exists();
        if (! $providesService) {
            throw ValidationException::withMessages([
                'service_id' => 'El colaborador no provee este servicio.',
            ]);
        }

        $appointment->appointmentServices()->create([
            'service_id' => $serviceId,
            'employee_id' => $employeeId,
        ]);

        return redirect()->back();
    }

    /**
     * Update the assigned employee on an existing appointment service line.
     */
    public function update(
        UpdateAppointmentServiceRequest $request,
        Appointment $appointment,
        AppointmentService $appointmentService,
    ): RedirectResponse {
        $this->authorize('update', $appointment);

        $employeeId = $request->validated('employee_id');

        /** @var Employee $employee */
        $employee = Employee::findOrFail($employeeId);

        // Multi-tenant defense: new employee must belong to the same business as the appointment.
        if ($employee->business_id !== $appointment->business_id) {
            throw ValidationException::withMessages([
                'employee_id' => 'El colaborador no pertenece al negocio de esta cita.',
            ]);
        }

        // Verify the appointment service line belongs to this appointment.
        if ($appointmentService->appointment_id !== $appointment->id) {
            abort(403, 'El servicio no pertenece a esta cita.');
        }

        // Business rule: new employee must offer the service on this line.
        $providesService = $employee->services()
            ->where('services.id', $appointmentService->service_id)
            ->exists();

        if (! $providesService) {
            throw ValidationException::withMessages([
                'employee_id' => 'El colaborador no provee el servicio asignado a esta línea.',
            ]);
        }

        $appointmentService->update(['employee_id' => $employeeId]);

        return redirect()->back()->with('success', 'Colaborador actualizado correctamente.');
    }
}
