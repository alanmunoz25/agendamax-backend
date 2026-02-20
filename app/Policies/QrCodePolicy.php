<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QrCode;
use App\Models\User;

class QrCodePolicy
{
    /**
     * Determine whether the user can view any QR codes.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Determine whether the user can view the QR code.
     */
    public function view(User $user, QrCode $qrCode): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $qrCode->business_id === $user->business_id;
    }

    /**
     * Determine whether the user can create QR codes.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Determine whether the user can delete the QR code.
     */
    public function delete(User $user, QrCode $qrCode): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBusinessAdmin() && $qrCode->business_id === $user->business_id;
    }
}
