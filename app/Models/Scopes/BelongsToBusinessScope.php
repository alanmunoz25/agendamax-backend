<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToBusinessScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Super admins bypass the scope entirely to see all records.
     * Regular users are filtered to their own business.
     */
    public function apply(Builder $query, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();

            // Super admin sees all records across all businesses
            if ($user->isSuperAdmin()) {
                return;
            }

            if ($user->business_id) {
                $query->where($model->getTable().'.business_id', $user->business_id);
            }
        }
    }
}
