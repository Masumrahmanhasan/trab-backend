<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class StoreOnboardingService implements StoreOnboardingServiceInterface
{
    public function __construct(
        protected PermissionSyncService $permissionSync,
    ) {}

    /**
     * Materialise a store's plan features as direct store-scoped permissions
     * on the owner, and make sure the owner holds the `owner` role for the
     * store.
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

        DB::transaction(function () use ($store) {
            $this->permissionSync->assignStoreOwnerRole($store);

            $permissions = $store->getAvailablePermissions();

            $store->owner->permissions()
                ->wherePivot('team_id', $store->id)
                ->detach();

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
     * Create a new store under the owner, enforcing the plan's store limit,
     * then grant the owner their plan permissions within the store.
     *
     * @throws InvalidArgumentException when the plan's store limit is reached
     */
    public function createStoreWithPlan(array $storeData, User $owner, int $planId): Store
    {
        $plan = Plan::findOrFail($planId);

        $activeSubscription = $owner->activeSubscription;

        if ($activeSubscription && $activeSubscription->plan_id !== $plan->id) {
            throw new InvalidArgumentException('Store plan must match your active subscription plan');
        }

        $this->assertStoreLimit($owner, $plan->max_stores);

        return DB::transaction(function () use ($storeData, $owner, $plan) {
            $store = Store::create([
                'name' => $storeData['name'],
                'slug' => $storeData['slug'],
                'description' => $storeData['description'] ?? null,
                'owner_id' => $owner->id,
                'plan_id' => $plan->id,
            ]);

            $this->permissionSync->assignStoreOwnerRole($store);
            $this->assignPlanPermissionsToOwner($store);

            Log::info('Store created with plan', [
                'store_id' => $store->id,
                'owner_id' => $owner->id,
                'plan_id' => $plan->id,
            ]);

            return $store;
        });
    }

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

    public function getOwnerAvailablePermissions(Store $store): Collection
    {
        return $store->getAvailablePermissions();
    }

    public function isPermissionAvailableToOwner(Store $store, string $permissionKey): bool
    {
        return $store->getAvailablePermissions()->contains('key', $permissionKey);
    }

    public function validatePermissionsForStore(Store $store, array $permissionKeys): void
    {
        if (! $store->plan) {
            throw new InvalidArgumentException('Store has no plan assigned');
        }

        $availablePermissions = $store->getAvailablePermissions()->pluck('key')->all();

        $invalidPermissions = array_diff($permissionKeys, $availablePermissions);

        if ($invalidPermissions !== []) {
            throw new InvalidArgumentException(
                'Permissions not available in your plan: '.implode(', ', $invalidPermissions)
            );
        }
    }

    /**
     * Enforce the plan's maximum number of stores for the owner.
     */
    protected function assertStoreLimit(User $owner, int $maxStores): void
    {
        if ($maxStores <= 0) {
            return;
        }

        $currentStores = $owner->ownedStores()->count();

        if ($currentStores >= $maxStores) {
            throw new InvalidArgumentException(
                "Store limit reached. Your plan allows {$maxStores} stores."
            );
        }
    }
}
