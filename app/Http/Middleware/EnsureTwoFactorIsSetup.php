<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect super_admin and business_admin users to the 2FA setup page
 * if they have not yet confirmed two-factor authentication.
 *
 * Client, employee, and lead roles are excluded — 2FA is an admin-only requirement.
 *
 * Apply this middleware to web routes that require admin context.
 * Exempt the 2FA settings route itself to avoid a redirect loop.
 */
class EnsureTwoFactorIsSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Only enforce for privileged roles.
        if (! $user->isSuperAdmin() && ! $user->isBusinessAdmin()) {
            return $next($request);
        }

        // Allow access to the 2FA settings page (avoid redirect loop).
        if ($request->routeIs('two-factor.show', 'password.confirm', 'two-factor.*')) {
            return $next($request);
        }

        // If the user has not yet confirmed 2FA, redirect to setup.
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('two-factor.show')
                ->with('status', 'Por seguridad, debes configurar la autenticación de dos factores.');
        }

        return $next($request);
    }
}
