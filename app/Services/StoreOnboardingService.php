<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreOnboardingService implements StoreOnboardingServiceInterface
{
    /**
     * Assign plan features as permissions to store owner
     */
    public function assignPlanPermissionsToOwner(Store $store): void
    {
        if (! $store->plan || ! $store->owner) {
            Log::warning('Cannot assign permissions: store has no plan or owner', [
                'store_id' => $store->id,
                'plan_id' => $store->plan_id,
                'owner_id' => $store->owner_id,
            ]);

            return;
        }

        $permissions = $store->getAvailablePermissions();

        DB::transaction(function () use ($store, $permissions) {
            // Remove existing permissions for this store context
            $store->owner->permissions()->wherePivot('team_id', $store->id)->detach();

            // Assign new permissions based on plan features
            foreach ($permissions as $permission) {
                $store->owner->assignPermissionTo($permission, $store->id);
            }

            Log::info('Permissions assigned to store owner', [
                'store_id' => $store->id,
                'owner_id' => $store->owner_id,
                'permissions_count' => $permissions->count(),
            ]);
        });
    }

    /**
     * Create a new store with plan assignment
     */
    public function createStoreWithPlan(array $storeData, User $owner, int $planId): Store
    {
        return DB::transaction(function () use ($storeData, $owner, $planId) {
            $store = Store::create([
                'name' => $storeData['name'],
                'slug' => $storeData['slug'],
                'description' => $storeData['description'] ?? null,
                'owner_id' => $owner->id,
                'plan_id' => $planId,
            ]);

            // Assign plan permissions to owner
            $this->assignPlanPermissionsToOwner($store);

            Log::info('Store created with plan', [
                'store_id' => $store->id,
                'owner_id' => $owner->id,
                'plan_id' => $planId,
            ]);

            return $store;
        });
    }

    /**
     * Update store plan and reassign permissions
     */
    public function updateStorePlan(Store $store, int $newPlanId): void
    {
        DB::transaction(function () use ($store, $newPlanId) {
            $oldPlanId = $store->plan_id;
            $store->update(['plan_id' => $newPlanId]);
            $this->assignPlanPermissionsToOwner($store);

            Log::info('Store plan updated', [
                'store_id' => $store->id,
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlanId,
            ]);
        });
    }

    /**
     * Get available permissions for store owner based on plan
     */
    public function getOwnerAvailablePermissions(Store $store): Collection
    {
        return $store->getAvailablePermissions();
    }

    /**
     * Validate if permission is available to store owner
     */
    public function isPermissionAvailableToOwner(Store $store, string $permissionKey): bool
    {
        return $store->getAvailablePermissions()->contains('key', $permissionKey);
    }

    /**
     * Validate if all permissions are available to store owner
     */
    public function validatePermissionsForStore(Store $store, array $permissionKeys): void
    {
        $availablePermissions = $store->getAvailablePermissions()->pluck('key')->toArray();

        foreach ($permissionKeys as $permissionKey) {
            if (! in_array($permissionKey, $availablePermissions)) {
                throw new \InvalidArgumentException("Permission '{$permissionKey}' is not available in your plan");
            }
        }
    }
}
