<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Doctor;

class AdminPolicy
{
    /**
     * Determine whether the user can view any administrators.
     */
    public function viewAny(Doctor|Admin $user): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can view the administrator.
     */
    public function view(Doctor|Admin $user, ?Admin $admin = null): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the user can delete the administrator.
     * An admin cannot delete their own account.
     */
    public function delete(Doctor|Admin $user, ?Admin $admin = null): bool
    {
        if (!($user instanceof Admin)) {
            return false;
        }

        if ($admin !== null && $user->admin_id === $admin->admin_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the administrator.
     */
    public function restore(Doctor|Admin $user, ?Admin $admin = null): bool
    {
        return $user instanceof Admin;
    }
}
