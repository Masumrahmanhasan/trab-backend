<?php

namespace App\Services;

use App\Enums\PermissionContext;
use App\Enums\Roles;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Materialises plan features into the permission system.
 *
 * Plans grant features; features carry a permission key (or are pure
 * capability flags). This service is responsible for translating a plan's
 * features into concrete grants, both at the global (platform) level and per
 * tenant (store) level, and for tearing those grants down when a subscription
 * ends.
 */
class PermissionSyncService
{
    /**
     * Grant the plan's global (platform-context) feature permissions directly
     * to the user.
     */
    public function syncGlobalPlanPermissions(User $user, Plan $plan, bool $trial = false): void
    {
        $permissionIds = $this->permissionIdsForPlan($plan, $trial, PermissionContext::PLATFORM);

        $user->syncPermissions($permissionIds);
    }

    /**
     * Assign the store owner role (scoped to the store) so the owner inherits
     * every store permission the platform currently defines.
     */
    public function assignStoreOwnerRole(Store $store): void
    {
        $ownerRole = Role::query()->where('key', Roles::OWNER->value)->first();

        if ($ownerRole) {
            $store->owner->assignRole($ownerRole, $store->id);
        }
    }

    /**
     * Remove every role and permission a user holds scoped to the store.
     */
    public function revokeStoreAccess(User $user, int $storeId): void
    {
        $user->roles()->wherePivot('team_id', $storeId)->detach();
        $user->permissions()->wherePivot('team_id', $storeId)->detach();

        app(PermissionCacheService::class)->clearUserPermissions($user->id, $storeId);
    }

    /**
     * Remove the plan-derived global permissions from a user (used when a
     * subscription is cancelled or expires).
     */
    public function revokeGlobalPlanPermissions(User $user): void
    {
        $globalKeys = $user->permissions()
            ->wherePivot('team_id', null)
            ->where('context', PermissionContext::PLATFORM->value)
            ->pluck('permissions.id');

        if ($globalKeys->isNotEmpty()) {
            $user->permissions()->detach($globalKeys);
        }

        app(PermissionCacheService::class)->clearUserPermissions($user->id);
    }

    /**
     * Re-materialise permissions for every user holding a current subscription,
     * so newly added features propagate to existing subscribers.
     *
     * Used by `features:sync` after launch.
     */
    public function resyncAllCurrentSubscribers(): void
    {
        $subscribers = User::query()
            ->whereHas('subscriptions', fn ($q) => $q->current())
            ->with('subscriptions', fn ($q) => $q->current()->with('plan'))
            ->get();

        DB::transaction(function () use ($subscribers) {
            foreach ($subscribers as $user) {
                $subscription = $user->subscriptions->first();

                if (! $subscription) {
                    continue;
                }

                $this->syncGlobalPlanPermissions($user, $subscription->plan, $subscription->isOnTrial());

                foreach ($user->ownedStores as $store) {
                    app(StoreOnboardingServiceInterface::class)->assignPlanPermissionsToOwner($store);
                }
            }
        });
    }

    /**
     * Permission IDs granted by a plan's features, filtered by context and,
     * during trial, by whether each feature is trial-enabled.
     *
     * @return array<int>
     */
    protected function permissionIdsForPlan(Plan $plan, bool $trial, PermissionContext $context): array
    {
        return $plan->features
            ->filter(fn (Feature $feature) => ! $trial || (bool) $feature->pivot->is_trial_allowed)
            ->filter(fn (Feature $feature) => $feature->context === $context->value)
            ->map(fn (Feature $feature) => $feature->getPermission())
            ->filter()
            ->map(fn (Permission $permission) => $permission->id)
            ->values()
            ->all();
    }
}
