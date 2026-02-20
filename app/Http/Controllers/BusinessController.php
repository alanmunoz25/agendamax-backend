<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Resources\QrCodeResource;
use App\Models\Business;
use App\Models\QrCode;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    use AuthorizesRequests;

    // ──────────────────────────────────────────────────
    // Super Admin: Resource CRUD (all businesses)
    // ──────────────────────────────────────────────────

    /**
     * List all businesses (super_admin).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Business::class);

        $query = Business::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $businesses = $query->withCount(['users', 'employees', 'services'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Businesses/Index', [
            'businesses' => $businesses,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new business (super_admin).
     */
    public function create(): Response
    {
        $this->authorize('create', Business::class);

        return Inertia::render('Businesses/Create');
    }

    /**
     * Store a newly created business (super_admin).
     */
    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $this->authorize('create', Business::class);

        $data = $request->validated();

        if (empty($data['invitation_code'])) {
            $data['invitation_code'] = strtoupper(Str::random(8));
        }

        $business = Business::create($data);

        return redirect()->route('businesses.show', $business)
            ->with('success', 'Business created successfully.');
    }

    /**
     * Display a specific business (super_admin).
     */
    public function show(Business $business): Response
    {
        $this->authorize('view', $business);

        $business->loadCount(['users', 'employees', 'services']);

        return Inertia::render('Businesses/Show', [
            'business' => $business,
        ]);
    }

    /**
     * Show the form for editing a specific business (super_admin).
     */
    public function edit(Business $business): Response
    {
        $this->authorize('update', $business);

        return Inertia::render('Businesses/Edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update a specific business (super_admin).
     */
    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $business->update($request->validated());

        return redirect()->route('businesses.show', $business)
            ->with('success', 'Business updated successfully.');
    }

    /**
     * Delete a business (super_admin).
     */
    public function destroy(Business $business): RedirectResponse
    {
        $this->authorize('delete', $business);

        $business->delete();

        return redirect()->route('businesses.index')
            ->with('success', 'Business deleted successfully.');
    }

    // ──────────────────────────────────────────────────
    // Business Admin: Own business routes (/business)
    // ──────────────────────────────────────────────────

    /**
     * Display the authenticated user's business profile.
     */
    public function showOwn(): Response
    {
        $business = auth()->user()->business;

        $this->authorize('view', $business);

        return Inertia::render('Business/Show', [
            'business' => $business,
            'qrCodes' => QrCodeResource::collection(
                QrCode::withoutGlobalScopes()
                    ->where('business_id', $business->id)
                    ->orderByDesc('created_at')
                    ->get()
            )->resolve(),
        ]);
    }

    /**
     * Show the form for editing the business profile.
     */
    public function editOwn(): Response
    {
        $business = auth()->user()->business;

        $this->authorize('update', $business);

        return Inertia::render('Business/Edit', [
            'business' => $business,
        ]);
    }

    /**
     * Update the business profile.
     */
    public function updateOwn(UpdateBusinessRequest $request): RedirectResponse
    {
        $business = auth()->user()->business;

        $this->authorize('update', $business);

        $business->update($request->validated());

        return redirect()->route('business.show')
            ->with('success', 'Business profile updated successfully.');
    }
}
