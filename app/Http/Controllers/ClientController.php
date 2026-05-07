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
        $statusFilter = request()->query('status_filter', 'all');

        $query = User::query()
            ->where('primary_business_id', $user->primary_business_id)
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

        // Enrich each client with pivot data from user_business table.
        // We filter by status_filter after pagination to keep the query simple.
        $businessId = $user->primary_business_id;

        $clients->getCollection()->transform(function (User $client) use ($businessId) {
            $pivotRow = $client->businesses()
                ->where('business_id', $businessId)
                ->first();

            $pivotStatus = $pivotRow?->pivot->status ?? null;
            $blockedReason = $pivotRow?->pivot->blocked_reason ?? null;

            $clientArray = $client->toArray();
            $clientArray['pivot_status'] = $pivotStatus;
            $clientArray['blocked_reason'] = $blockedReason;

            return $clientArray;
        });

        // Apply status_filter post-pagination (collection filter).
        if (in_array($statusFilter, ['active', 'blocked'], true)) {
            $filtered = $clients->getCollection()->filter(
                fn ($client) => ($client['pivot_status'] ?? null) === $statusFilter
            )->values();

            $clients->setCollection($filtered);
        }

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => array_merge(
                request()->only(['search', 'sort', 'direction']),
                ['status_filter' => $statusFilter]
            ),
            'can' => [
                'create' => $user->can('create', User::class),
                'block' => $user->isBusinessAdmin() || $user->isSuperAdmin(),
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

        $validated = $request->validated();
        $client = new User;
        $client->fill([
            ...$validated,
            'password' => bcrypt(str()->random(16)), // Random password, user will reset via email
        ]);
        $client->forceFill([
            'business_id' => auth()->user()->primary_business_id,
            'role' => 'client',
        ])->save();

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
        if ($client->primary_business_id !== $user->primary_business_id) {
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
            $client->primary_business_id
        );

        // Enrich client with pivot data (block status) for the current business.
        $pivotRow = $client->businesses()
            ->where('business_id', $user->primary_business_id)
            ->first();

        $clientData = $client->toArray();
        $clientData['pivot_status'] = $pivotRow?->pivot->status ?? null;
        $clientData['blocked_reason'] = $pivotRow?->pivot->blocked_reason ?? null;
        $clientData['blocked_at'] = $pivotRow?->pivot->blocked_at?->toISOString() ?? null;

        return Inertia::render('Clients/Show', [
            'client' => $clientData,
            'loyalty_progress' => $loyaltyProgress,
            'recent_appointments' => $client->appointments,
            'recent_stamps' => $client->stamps,
            'can' => [
                'block' => $user->isBusinessAdmin() || $user->isSuperAdmin(),
            ],
        ]);
    }
}
