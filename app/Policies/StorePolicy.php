<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;

    public function view(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasActiveSubscription();
    }

    public function update(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function manageStaff(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function viewStaff(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function assignStaff(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function removeStaff(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }

    public function updateStaffPermissions(User $user, Store $store): bool
    {
        return $user->id === $store->owner_id || $user->hasRole('super-admin');
    }
}
