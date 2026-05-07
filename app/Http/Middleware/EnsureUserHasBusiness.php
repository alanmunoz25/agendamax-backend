<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! auth()->check()) {
            return $next($request);
        }

        // Clients navigate cross-business — no business context required.
        if ($user->isClient()) {
            return $next($request);
        }

        if (! $user->primary_business_id && ! $user->isSuperAdmin()) {
            abort(403, 'User does not belong to a business.');
        }

        return $next($request);
    }
}
