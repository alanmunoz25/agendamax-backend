<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    /**
     * Display a listing of clients.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $user = auth()->user();

        $query = User::query()
            ->where('business_id', $user->business_id)
            ->whereIn('role', ['client', 'lead']);

        // Employees only see clients who have appointments assigned to them
        if ($user->isEmployee()) {
            $employeeId = $user->employee?->id;
            if ($employeeId) {
                $query->whereHas('appointments', function ($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $clients = $query
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->withCount(['appointments', 'stamps'])
            ->with(['appointments' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy(request('sort', 'name'), request('direction', 'asc'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => request()->only(['search', 'sort', 'direction']),
            'can' => [
                'create' => $user->can('create', User::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new client.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Clients/Create');
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $client = User::create([
            ...$request->validated(),
            'business_id' => auth()->user()->business_id,
            'role' => 'client',
            'password' => bcrypt(str()->random(16)), // Random password, user will reset via email
        ]);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    /**
     * Display the specified client.
     */
    public function show(User $client): Response
    {
        $user = auth()->user();

        // Ensure client belongs to current business
        if ($client->business_id !== $user->business_id) {
            abort(404);
        }

        $this->authorize('view', $client);

        // Employees can only view clients who have appointments with them
        if ($user->isEmployee()) {
            $employeeId = $user->employee?->id;
            $hasAppointment = $employeeId && Appointment::where('employee_id', $employeeId)
                ->where('client_id', $client->id)
                ->exists();

            if (! $hasAppointment) {
                abort(403);
            }
        }

        // Load relationships — scope appointments for employees
        $appointmentQuery = function ($query) use ($user) {
            $query->with(['service', 'employee.user'])
                ->latest('scheduled_at')
                ->limit(10);

            if ($user->isEmployee()) {
                $query->where('employee_id', $user->employee?->id);
            }
        };

        $client->load([
            'appointments' => $appointmentQuery,
            'stamps' => function ($query) {
                $query->with('visit')
                    ->latest('earned_at')
                    ->limit(10);
            },
        ]);

        // Get loyalty progress
        $loyaltyProgress = $this->loyaltyService->getProgress(
            $client->id,
            $client->business_id
        );

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'loyalty_progress' => $loyaltyProgress,
            'recent_appointments' => $client->appointments,
            'recent_stamps' => $client->stamps,
        ]);
    }
}
