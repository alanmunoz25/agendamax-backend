<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollAdjustmentPolicy
{
    /**
     * Determine whether the user can create an adjustment for the given period.
     * The period must be open and belong to the user's business.
     *
     * Called via: $user->can('create', [PayrollAdjustment::class, $period])
     */
    public function create(User $user, PayrollPeriod $period): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin()
            && $user->business_id === $period->business_id
            && $period->status === 'open';
    }
}
