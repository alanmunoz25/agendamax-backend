<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class ClientEnrollmentPolicy
{
    /**
     * Determine whether the actor can block a client in the given business.
     * Only the business_admin of that specific business or a super_admin may block.
     */
    public function block(User $actor, User $target, Business $business): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->isBusinessAdmin()) {
            return $actor->business_id === $business->id;
        }

        return false;
    }

    /**
     * Determine whether the actor can unblock a client in the given business.
     */
    public function unblock(User $actor, User $target, Business $business): bool
    {
        return $this->block($actor, $target, $business);
    }
}
