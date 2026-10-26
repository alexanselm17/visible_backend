<?php

namespace App\Support;

use App\Models\User;

class AdminAccess
{
    public static function allows(string $area, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        if ($user->isAdmin() || $user->isDeveloper()) {
            return true;
        }

        return $user->isManager() && match ($area) {
            'campaigns', 'reports', 'fraud' => true,
            'users' => (bool) $user->hasAnyPermission(['manage_users', 'users_roles']),
            default => false,
        };
    }
}
