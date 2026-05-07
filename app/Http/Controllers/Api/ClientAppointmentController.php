<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\CrossBusinessAppointmentResource;
use App\Models\Appointment;
use App\Models\Business;
use App\Support\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * GET /api/v1/client/appointments
 *
 * Cross-business appointments history for the authenticated client.
 *
 * Query parameters:
 *   scope    string  'all' (default) | 'business'
 *   status   string  optional — 'pending' | 'confirmed' | 'completed' | 'cancelled'
 *   from     string  optional — YYYY-MM-DD lower bound on scheduled_at
 *   to       string  optional — YYYY-MM-DD upper bound on scheduled_at
 *   per_page integer optional — 1–50, default 20 (only used for scope=business)
 */
class ClientAppointmentController extends Controller
{
    private const STATUS_OPTIONS = ['pending', 'confirmed', 'completed', 'cancelled'];

    /**
     * Return the authenticated client's appointments.
     *
     * scope=all  → grouped by business, no BelongsToBusinessScope, no pagination
     * scope=business → filtered by BusinessContext, paginated, standard resource collection
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $validated = $request->validate([
            'scope' => 'nullable|string|in:all,business',
            'status' => 'nullable|string|in:pending,confirmed,completed,cancelled',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $scope = $validated['scope'] ?? 'all';

        if ($scope === 'business') {
            return $this->businessScope($validated);
        }

        return $this->allScope($validated);
    }

    /**
     * scope=all — cross-business grouped response.
     *
     * @param  array<string, mixed>  $filters
     */
    private function allScope(array $filters): JsonResponse
    {
        $user = Auth::user();

        /** @var \Illuminate\Support\Collection<int, Business> $enrolledBusinesses */
        $enrolledBusinesses = $user->businesses()
            ->wherePivotIn('status', ['active', 'blocked'])
            ->get();

        $groups = $enrolledBusinesses->map(function (Business $business) use ($user, $filters) {
            $query = Appointment::withoutGlobalScopes()
                ->where('client_id', $user->id)
                ->where('business_id', $business->id)
                ->with(['business', 'service', 'employee.user', 'services'])
                ->orderBy('scheduled_at', 'desc');

            $this->applyFilters($query, $filters);

            $appointments = $query->get();

            return new CrossBusinessAppointmentResource([
                'business' => $business,
                'appointments' => $appointments,
                'is_blocked' => $business->pivot->status === 'blocked',
            ]);
        });

        $totalAppointments = $groups->sum(fn (CrossBusinessAppointmentResource $group) => $group->resource['appointments']->count());

        $activeEnrollments = $enrolledBusinesses->filter(fn (Business $b) => $b->pivot->status === 'active')->count();
        $blockedEnrollments = $enrolledBusinesses->filter(fn (Business $b) => $b->pivot->status === 'blocked')->count();

        return response()->json([
            'data' => $groups->values(),
            'meta' => [
                'total_businesses' => $enrolledBusinesses->count(),
                'total_appointments' => $totalAppointments,
                'active_enrollments' => $activeEnrollments,
                'blocked_enrollments' => $blockedEnrollments,
            ],
        ]);
    }

    /**
     * scope=business — standard paginated response within a single business context.
     *
     * Resolves the business_id from BusinessContext (set by ResolveBusinessContext middleware
     * when use_business_context flag is on) or directly from the X-Business-Id header as a
     * fallback, so the endpoint works regardless of the feature flag state.
     *
     * @param  array<string, mixed>  $filters
     */
    private function businessScope(array $filters): JsonResponse|AnonymousResourceCollection
    {
        $businessId = BusinessContext::current();

        // Fallback: read directly from header when middleware feature flag is off.
        if ($businessId === null) {
            $rawHeader = request()->header('X-Business-Id');

            if ($rawHeader !== null && ctype_digit((string) $rawHeader) && (int) $rawHeader > 0) {
                $businessId = (int) $rawHeader;
            }
        }

        if ($businessId === null) {
            return response()->json([
                'message' => 'X-Business-Id header required for business scope',
            ], 422);
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 20;

        $query = Appointment::withoutGlobalScopes()
            ->where('client_id', Auth::id())
            ->where('business_id', $businessId)
            ->with(['service', 'employee.user', 'business', 'services'])
            ->orderBy('scheduled_at', 'desc');

        $this->applyFilters($query, $filters);

        $appointments = $query->paginate($perPage);

        return AppointmentResource::collection($appointments);
    }

    /**
     * Apply optional status / date filters to the query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Appointment>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('scheduled_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('scheduled_at', '<=', $filters['to']);
        }
    }
}
