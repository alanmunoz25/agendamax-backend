<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\PayrollRecord;
use App\Models\User;

class PayrollRecordPolicy
{
    /**
     * Determine whether the user can view the payroll record.
     */
    public function view(User $user, PayrollRecord $record): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id === $record->business_id;
        }

        if ($user->isEmployee()) {
            return $user->employee?->id === $record->employee_id;
        }

        return false;
    }

    /**
     * Determine whether the user can approve payroll records in a period.
     * State validity (draft status) is enforced by PayrollService, not here.
     */
    public function approve(User $user, PayrollRecord $record): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $record->business_id;
    }

    /**
     * Determine whether the user can mark the payroll record as paid.
     * State validity (approved status) is enforced by PayrollService, not here.
     */
    public function markPaid(User $user, PayrollRecord $record): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $record->business_id;
    }

    /**
     * Determine whether the user can void the payroll record.
     * State validity (non-voided status) is enforced by PayrollService, not here.
     */
    public function void(User $user, PayrollRecord $record): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $record->business_id;
    }
}
