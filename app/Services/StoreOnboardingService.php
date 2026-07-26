<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreOnboardingService
{
    /**
     * Assign plan features as permissions to store owner
     */
    public function assignPlanPermissionsToOwner(Store $store): void
    {
        if (!$store->plan || !$store->owner) {
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

            return $store;
        });
    }

    /**
     * Update store plan and reassign permissions
     */
    public function updateStorePlan(Store $store, int $newPlanId): void
    {
        DB::transaction(function () use ($store, $newPlanId) {
            $store->update(['plan_id' => $newPlanId]);
            $this->assignPlanPermissionsToOwner($store);
        });
    }

    /**
     * Get available permissions for store owner based on plan
     */
    public function getOwnerAvailablePermissions(Store $store): \Illuminate\Support\Collection
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
            if (!in_array($permissionKey, $availablePermissions)) {
                throw new \InvalidArgumentException("Permission '{$permissionKey}' is not available in your plan");
            }
        }
    }
}
