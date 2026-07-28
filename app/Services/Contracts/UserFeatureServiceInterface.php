<?php

namespace App\Services\Contracts;

use App\Models\Feature;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserFeatureServiceInterface
{
    /**
     * Get features available to the user.
     */
    public function getUserFeatures(User $user): Collection;

    /**
     * Get features available to the user for a specific store.
     */
    public function getUserFeaturesForStore(User $user, Store $store): Collection;

    /**
     * Check if user has access to a specific feature.
     */
    public function userHasAccessToFeature(User $user, Feature $feature): bool;

    /**
     * Check if user has access to a store.
     */
    public function userHasAccessToStore(User $user, Store $store): bool;

    /**
     * Get user's permissions with associated features.
     */
    public function getUserPermissionsWithFeatures(User $user): array;

    /**
     * Get user's permissions for a specific store with associated features.
     */
    public function getUserPermissionsForStoreWithFeatures(User $user, Store $store): array;
}
