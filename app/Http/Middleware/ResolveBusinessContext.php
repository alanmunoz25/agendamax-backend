<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and sets the BusinessContext for the current request.
 *
 * Resolution order:
 *   1. X-Business-Id header — validates enrollment, sets context.
 *   2. {business} route parameter — uses model binding value.
 *   3. Legacy fallback — uses auth()->user()->primary_business_id for admin/employee/super_admin.
 *   4. Client without context — no context set (cross-biz or pre-enrollment).
 *
 * When the feature flag `agendamax.use_business_context` is false this
 * middleware is a no-op, preserving legacy behaviour with zero overhead.
 */
class ResolveBusinessContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always clear any leftover context from a previous request (static property).
        BusinessContext::clear();

        // Feature flag guard — no-op when disabled.
        if (! config('agendamax.use_business_context')) {
            return $next($request);
        }

        $user = $request->user();

        // Not authenticated — no context to set.
        if ($user === null) {
            return $next($request);
        }

        // 1. X-Business-Id header (primary signal from mobile/SPA clients).
        if ($request->hasHeader('X-Business-Id')) {
            $rawId = $request->header('X-Business-Id');

            if (! ctype_digit((string) $rawId) || (int) $rawId <= 0) {
                abort(422, 'X-Business-Id must be a positive integer.');
            }

            $businessId = (int) $rawId;

            // Validate that the authenticated user has a pivot row (any non-left status).
            $pivot = $user->businesses()
                ->wherePivot('business_id', $businessId)
                ->wherePivotIn('status', ['active', 'blocked'])
                ->first();

            if ($pivot === null) {
                abort(403, 'Not enrolled in business.');
            }

            // Set context even if blocked — scope will allow history reads.
            // AppointmentPolicy::create checks canBookIn() to block new bookings.
            BusinessContext::set($businessId);

            return $next($request);
        }

        // 2. {business} route parameter resolved by Eloquent model binding.
        $businessModel = $request->route('business');

        if ($businessModel instanceof \App\Models\Business) {
            BusinessContext::set($businessModel->id);

            return $next($request);
        }

        // 3. Legacy fallback: admin / employee / super_admin use their primary_business_id.
        if ($user->isSuperAdmin() || $user->isBusinessAdmin() || $user->isEmployee()) {
            if ($user->primary_business_id !== null) {
                BusinessContext::set((int) $user->primary_business_id);
            }

            return $next($request);
        }

        // 4. Client without an explicit context — no filter applied.
        // BelongsToBusinessScope will skip filtering when BusinessContext::current() is null,
        // allowing cross-business history queries once that feature is built (F4).
        return $next($request);
    }
}
