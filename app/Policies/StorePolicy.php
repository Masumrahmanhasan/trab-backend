<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Anyone with a role scoped to the store, the owner, or a super admin.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id
            || $user->hasRole('super-admin')
            || $user->roles()->wherePivot('team_id', $store->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasActiveSubscription();
    }

    public function update(User $user, Store $store): bool
    {
        return $this->canManage($user, $store);
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function manageStaff(User $user, Store $store): bool
    {
        return $this->canManage($user, $store, 'store.staff.manage');
    }

    public function viewStaff(User $user, Store $store): bool
    {
        return $this->canView($user, $store, 'store.staff.view');
    }

    public function assignStaff(User $user, Store $store): bool
    {
        return $this->canManage($user, $store, 'store.staff.assign');
    }

    public function removeStaff(User $user, Store $store): bool
    {
        return $this->canManage($user, $store, 'store.staff.remove');
    }

    public function updateStaffPermissions(User $user, Store $store): bool
    {
        return $this->canManage($user, $store, 'store.permissions.assign');
    }

    /**
     * Owners and super admins can always manage; otherwise the user needs the
     * given store-scoped permission (falling back to generic store management
     * when no specific permission is provided).
     */
    protected function canManage(User $user, Store $store, ?string $permission = null): bool
    {
        if ($user->id === $store->owner_id || $user->hasRole('super-admin')) {
            return true;
        }

        $permission ??= 'store.settings.manage';

        return $user->hasPermissionTo($permission, $store->id);
    }

    protected function canView(User $user, Store $store, string $permission): bool
    {
        return $this->canManage($user, $store, $permission);
    }
}
