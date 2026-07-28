<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\UserFeatureServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UserFeatureService implements UserFeatureServiceInterface
{
    /**
     * Get features available to the user.
     */
    public function getUserFeatures(User $user): Collection
    {
        // If user is super admin, return all features
        if ($user->hasRole('super-admin')) {
            Log::info('Super admin accessing all features', ['user_id' => $user->id]);

            return Feature::active()->get();
        }

        // Get user's active subscription plan
        $subscription = $user->activeSubscription;

        if (! $subscription) {
            Log::info('User has no active subscription', ['user_id' => $user->id]);

            return collect();
        }

        // Get features from the subscription plan
        $planFeatures = $subscription->plan->features;

        // Get additional features based on user's direct permissions
        $userPermissions = $user->permissions;
        $permissionKeys = $userPermissions->pluck('key')->toArray();

        // Get features that match user's permissions
        $additionalFeatures = Feature::whereIn('permission_key', $permissionKeys)
            ->active()
            ->get();

        // Merge and deduplicate features
        $features = $planFeatures->merge($additionalFeatures)->unique('id');

        Log::info('User features retrieved', [
            'user_id' => $user->id,
            'plan_features_count' => $planFeatures->count(),
            'additional_features_count' => $additionalFeatures->count(),
            'total_features_count' => $features->count(),
        ]);

        return $features;
    }

    /**
     * Get features available to the user for a specific store.
     */
    public function getUserFeaturesForStore(User $user, Store $store): Collection
    {
        // If user is super admin, return all features
        if ($user->hasRole('super-admin')) {
            Log::info('Super admin accessing all features for store', [
                'user_id' => $user->id,
                'store_id' => $store->id,
            ]);

            return Feature::active()->get();
        }

        // If user is the store owner, get features from store's plan
        if ($store->owner_id === $user->id) {
            if ($store->plan) {
                Log::info('Store owner accessing plan features', [
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                    'plan_id' => $store->plan->id,
                ]);

                return $store->plan->features;
            }

            // If store has no plan, check user's subscription
            $subscription = $user->activeSubscription;
            if ($subscription) {
                Log::info('Store owner accessing subscription plan features', [
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                    'subscription_id' => $subscription->id,
                ]);

                return $subscription->plan->features;
            }

            Log::warning('Store owner has no plan or subscription', [
                'user_id' => $user->id,
                'store_id' => $store->id,
            ]);

            return collect();
        }

        // For staff members, get features based on their role in the store
        $userRoles = $user->roles()->wherePivot('team_id', $store->id)->get();

        if ($userRoles->isEmpty()) {
            Log::info('User has no roles in store', [
                'user_id' => $user->id,
                'store_id' => $store->id,
            ]);

            return collect();
        }

        // Get features based on user's permissions in this store
        $userPermissions = $user->permissions()->wherePivot('team_id', $store->id)->get();
        $permissionKeys = $userPermissions->pluck('key')->toArray();

        // Get features that match user's permissions
        $features = Feature::whereIn('permission_key', $permissionKeys)
            ->active()
            ->get();

        // Also include features from the store's plan if the user has the owner role
        if ($userRoles->contains('key', 'owner')) {
            if ($store->plan) {
                $planFeatures = $store->plan->features;
                $features = $features->merge($planFeatures)->unique('id');
            }
        }

        Log::info('Staff member features retrieved', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'roles' => $userRoles->pluck('key')->toArray(),
            'features_count' => $features->count(),
        ]);

        return $features;
    }

    /**
     * Check if user has access to a specific feature.
     */
    public function userHasAccessToFeature(User $user, Feature $feature): bool
    {
        // Super admin has access to all features
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Check if user has the permission associated with this feature
        if ($feature->permission_key) {
            return $user->hasPermissionTo($feature->permission_key);
        }

        // Check if feature is in user's subscription plan
        $subscription = $user->activeSubscription;
        if ($subscription) {
            return $subscription->plan->features->contains('id', $feature->id);
        }

        return false;
    }

    /**
     * Check if user has access to a store.
     */
    public function userHasAccessToStore(User $user, Store $store): bool
    {
        // Super admin has access to all stores
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Store owner has access
        if ($store->owner_id === $user->id) {
            return true;
        }

        // Check if user has any role in this store
        return $user->roles()->wherePivot('team_id', $store->id)->exists();
    }

    /**
     * Get user's permissions with associated features.
     */
    public function getUserPermissionsWithFeatures(User $user): array
    {
        // Get all user permissions
        $permissions = $user->permissions;

        // Get features associated with these permissions
        $features = Feature::whereIn('permission_key', $permissions->pluck('key'))
            ->active()
            ->get();

        return [
            'permissions' => $permissions,
            'features' => $features,
        ];
    }

    /**
     * Get user's permissions for a specific store with associated features.
     */
    public function getUserPermissionsForStoreWithFeatures(User $user, Store $store): array
    {
        // Get user's permissions for this store
        $permissions = $user->permissions()->wherePivot('team_id', $store->id)->get();

        // Get features associated with these permissions
        $features = Feature::whereIn('permission_key', $permissions->pluck('key'))
            ->active()
            ->get();

        return [
            'permissions' => $permissions,
            'features' => $features,
        ];
    }
}
