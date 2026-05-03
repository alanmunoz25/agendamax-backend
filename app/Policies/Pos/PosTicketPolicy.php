<?php

declare(strict_types=1);

namespace App\Policies\Pos;

use App\Models\PosTicket;
use App\Models\User;

/**
 * Authorization policy for POS tickets.
 *
 * Employees (cashiers) can create tickets and view their business's tickets.
 * Void and refund operations require business_admin — these affect financial records.
 */
class PosTicketPolicy
{
    /**
     * Determine whether the user can list tickets for their business.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->isBusinessAdmin() && $user->business_id !== null)
            || ($user->isEmployee() && $user->business_id !== null);
    }

    /**
     * Determine whether the user can view a specific ticket.
     */
    public function view(User $user, PosTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBusinessAdmin() || $user->isEmployee()) {
            return $user->business_id === $ticket->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create a new POS ticket.
     * Employees (cashiers) are the primary creators at the counter.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isBusinessAdmin()
            || $user->isEmployee();
    }

    /**
     * Determine whether the user can void a ticket.
     * Voiding has financial impact — restricted to business_admin.
     */
    public function void(User $user, PosTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $ticket->business_id;
    }

    /**
     * Determine whether the user can issue a refund (credit note) for a ticket.
     */
    public function refund(User $user, PosTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $user->business_id === $ticket->business_id;
    }
}
