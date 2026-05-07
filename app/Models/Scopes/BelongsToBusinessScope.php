<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToBusinessScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Super admins bypass the scope entirely to see all records.
     * When the `agendamax.use_business_context` feature flag is enabled,
     * the active business_id is read from BusinessContext::current() which
     * is set by ResolveBusinessContext middleware. When the flag is off,
     * the legacy path reads directly from the user's primary business_id.
     */
    public function apply(Builder $query, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Super admin sees all records across all businesses.
        if ($user->isSuperAdmin()) {
            return;
        }

        if (config('agendamax.use_business_context')) {
            // New path — read context set by ResolveBusinessContext middleware.
            $businessId = BusinessContext::current();

            // Web fallback: ResolveBusinessContext is registered only on the API
            // middleware group (bootstrap/app.php). Inertia/web requests therefore
            // never resolve a context and would otherwise leave admin/employee
            // queries unfiltered. Fall back to the user's primary_business_id.
            // Clients are intentionally excluded so cross-biz history (F4) keeps
            // working when no header is sent.
            if ($businessId === null && ($user->isBusinessAdmin() || $user->isEmployee())) {
                $businessId = $user->primary_business_id;
            }

            if ($businessId !== null) {
                $query->where($model->getTable().'.business_id', $businessId);
            }

            return;
        }

        // Legacy path (default when flag is false) — preserves existing behaviour.
        if ($user->business_id) {
            $query->where($model->getTable().'.business_id', $user->business_id);
        }
    }
}
