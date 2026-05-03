<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('manageUsers', User::class);

        $query = User::query();

        // Super admin sees all users; business_admin sees only their business users
        if (! $request->user()->isSuperAdmin()) {
            $query->where('business_id', $request->user()->business_id);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->value());
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->integer('business_id'));
        }

        $users = $query->with('business')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'businesses' => $request->user()->isSuperAdmin()
                ? Business::orderBy('name')->get(['id', 'name'])
                : [],
            'filters' => $request->only(['search', 'role', 'business_id']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manageUsers', User::class);

        return Inertia::render('Users/Create', [
            'businesses' => auth()->user()->isSuperAdmin()
                ? Business::orderBy('name')->get(['id', 'name'])
                : [],
            'availableRoles' => $this->getAvailableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageUsers', User::class);

        $currentUser = $request->user();
        $allowedRoles = $this->getAvailableRoles();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', $allowedRoles)],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        // Business admin: force business_id to their own
        if (! $currentUser->isSuperAdmin()) {
            $validated['business_id'] = $currentUser->business_id;
        }

        // Prevent business_admin from creating super_admin users
        if (! $currentUser->isSuperAdmin() && $validated['role'] === 'super_admin') {
            abort(403, 'Only super admins can assign the super_admin role.');
        }

        // role and business_id are excluded from $fillable.
        // Separate them for explicit forceFill assignment from this trusted action.
        $roleToAssign = $validated['role'];
        $businessIdToAssign = $validated['business_id'] ?? null;

        $user = new User;
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);
        $user->forceFill([
            'role' => $roleToAssign,
            'business_id' => $businessIdToAssign,
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'targetUser' => $user->load('business'),
            'businesses' => auth()->user()->isSuperAdmin()
                ? Business::orderBy('name')->get(['id', 'name'])
                : [],
            'availableRoles' => $this->getAvailableRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $currentUser = $request->user();
        $allowedRoles = $this->getAvailableRoles();

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', $allowedRoles)],
            'business_id' => ['nullable', 'exists:businesses,id'],
        ]);

        // Only super_admin can change business_id
        if (! $currentUser->isSuperAdmin()) {
            unset($validated['business_id']);
        }

        // Prevent business_admin from assigning super_admin role
        if (! $currentUser->isSuperAdmin() && $validated['role'] === 'super_admin') {
            abort(403, 'Only super admins can assign the super_admin role.');
        }

        // role and business_id are excluded from $fillable — use forceFill from
        // this policy-authorized controller action.
        $user->forceFill($validated)->save();

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableRoles(): array
    {
        if (auth()->user()->isSuperAdmin()) {
            return ['super_admin', 'business_admin', 'employee', 'client'];
        }

        return ['employee', 'client'];
    }
}
