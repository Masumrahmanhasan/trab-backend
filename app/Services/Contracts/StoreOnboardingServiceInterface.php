<?php

namespace App\Services\Contracts;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

interface StoreOnboardingServiceInterface
{
    /**
     * Assign plan features as permissions to store owner
     */
    public function assignPlanPermissionsToOwner(Store $store): void;

    /**
     * Create a new store with plan assignment
     */
    public function createStoreWithPlan(array $storeData, User $owner, int $planId): Store;

    /**
     * Update store plan and reassign permissions
     */
    public function updateStorePlan(Store $store, int $newPlanId): void;

    /**
     * Get available permissions for store owner based on plan
     */
    public function getOwnerAvailablePermissions(Store $store): Collection;

    /**
     * Validate if permission is available to store owner
     */
    public function isPermissionAvailableToOwner(Store $store, string $permissionKey): bool;

    /**
     * Validate if all permissions are available to store owner
     */
    public function validatePermissionsForStore(Store $store, array $permissionKeys): void;
}
