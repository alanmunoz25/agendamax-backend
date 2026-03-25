<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $user = auth()->user();

        $query = Appointment::query()
            ->with(['service', 'employee.user', 'client']);

        // Role-based query filtering
        if ($user->isEmployee()) {
            $employeeId = $user->employee?->id;
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isClient()) {
            $query->where('client_id', $user->id);
        }

        // Search by client name or email
        if ($search = $request->input('search')) {
            $query->whereHas('client', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            );
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by employee
        if ($employeeId = $request->input('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by service
        if ($serviceId = $request->input('service_id')) {
            $query->where('service_id', $serviceId);
        }

        // Filter by date range
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('scheduled_at', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->whereDate('scheduled_at', '<=', $endDate);
        }

        // View mode determines ordering and pagination
        $viewMode = $request->input('view', 'list');

        if ($viewMode === 'calendar') {
            // For calendar view, get all appointments in the requested month
            $month = $request->input('month', now()->format('Y-m'));
            $startOfMonth = Carbon::parse($month)->startOfMonth();
            $endOfMonth = Carbon::parse($month)->endOfMonth();

            $appointments = $query
                ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
                ->orderBy('scheduled_at', 'asc')
                ->get();
        } else {
            // For list view, use pagination
            $appointments = $query
                ->orderBy('scheduled_at', $request->input('direction', 'desc'))
                ->paginate(20)
                ->withQueryString();
        }

        // Get filter options (role-aware)
        $employees = [];
        $services = [];

        if (! $user->isClient()) {
            if (! $user->isEmployee()) {
                $employees = Employee::query()
                    ->with('user:id,name')
                    ->where('business_id', $user->business_id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get(['id', 'user_id']);
            }

            $services = Service::query()
                ->where('business_id', $user->business_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'employees' => $employees,
            'services' => $services,
            'filters' => $request->only(['search', 'status', 'employee_id', 'service_id', 'start_date', 'end_date', 'view', 'month']),
            'statuses' => ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'],
            'can' => [
                'create' => $user->can('create', Appointment::class),
                'manage' => $user->isBusinessAdmin() || $user->isSuperAdmin(),
                'cancel' => ! $user->isEmployee(),
                'filter_employees' => ! $user->isEmployee() && ! $user->isClient(),
                'filter_services' => ! $user->isClient(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Appointment::class);

        $employees = Employee::query()
            ->with('user:id,name,email')
            ->where('business_id', auth()->user()->business_id)
            ->where('is_active', true)
            ->get(['id', 'user_id', 'photo_url']);

        $services = Service::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'price', 'duration']);

        // Get clients in the business (for business admin)
        $clients = User::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('role', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        return Inertia::render('Appointments/Create', [
            'employees' => $employees,
            'services' => $services,
            'clients' => $clients,
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Appointment::class);

        try {
            $appointment = $this->appointmentService->createAppointment([
                ...$request->validated(),
                'business_id' => auth()->user()->business_id,
                'client_id' => $request->input('client_id') ?? auth()->id(),
            ]);

            return redirect()->route('appointments.show', $appointment)
                ->with('success', 'Appointment created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function show(Appointment $appointment): Response
    {
        $this->authorize('view', $appointment);

        $appointment->load([
            'service',
            'services',
            'employee.user',
            'employee.services',
            'client',
            'visit',
        ]);

        return Inertia::render('Appointments/Show', [
            'appointment' => $appointment,
            'can' => [
                'edit' => auth()->user()->can('update', $appointment),
                'cancel' => auth()->user()->can('delete', $appointment),
            ],
        ]);
    }

    public function edit(Appointment $appointment): Response
    {
        $this->authorize('update', $appointment);

        $appointment->load(['service', 'employee.user', 'client']);

        $employees = Employee::query()
            ->with('user:id,name,email')
            ->where('business_id', auth()->user()->business_id)
            ->where('is_active', true)
            ->get(['id', 'user_id', 'photo_url']);

        $services = Service::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'price', 'duration']);

        return Inertia::render('Appointments/Edit', [
            'appointment' => $appointment,
            'employees' => $employees,
            'services' => $services,
            'statuses' => ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'],
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        try {
            $appointment->update($request->validated());

            return redirect()->route('appointments.show', $appointment)
                ->with('success', 'Appointment updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorize('delete', $appointment);

        try {
            // Use the service to cancel the appointment (sends notifications, etc.)
            $this->appointmentService->cancelAppointment(
                $appointment->id,
                'Cancelled by business admin'
            );

            return redirect()->route('appointments.index')
                ->with('success', 'Appointment cancelled successfully.');
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get available time slots for a given employee, service, and date.
     */
    public function availability(Request $request): \Illuminate\Http\JsonResponse
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
