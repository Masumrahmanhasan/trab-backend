<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatureResource;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Store;
use App\Services\Contracts\UserFeatureServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    use ApiResponse;

    protected UserFeatureServiceInterface $featureService;

    public function __construct(UserFeatureServiceInterface $featureService)
    {
        $this->featureService = $featureService;
    }

    /**
     * Get features available to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        $features = $this->featureService->getUserFeatures($request->user());

        return $this->ok('User features retrieved successfully', FeatureResource::collection($features));
    }

    /**
     * Get features available to the user for a specific store.
     */
    public function storeFeatures(Request $request, Store $store): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        $user = $request->user();

        // Check if user has access to this store
        if (! $this->featureService->userHasAccessToStore($user, $store)) {
            return $this->forbidden('You do not have access to this store');
        }

        $features = $this->featureService->getUserFeaturesForStore($user, $store);

        return $this->ok('Store features retrieved successfully', FeatureResource::collection($features));
    }

    /**
     * Get feature details by ID.
     */
    public function show(Request $request, Feature $feature): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        $user = $request->user();

        // Check if user has access to this feature
        if (! $this->featureService->userHasAccessToFeature($user, $feature)) {
            return $this->forbidden('You do not have access to this feature');
        }

        return $this->ok('Feature details retrieved successfully', new FeatureResource($feature));
    }

    /**
     * Get user's permissions with associated features.
     */
    public function permissions(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        $user = $request->user();

        // Super admin gets all super admin permissions
        if ($user->hasRole('super-admin')) {
            $permissions = Permission::superAdmin()->get();

            return $this->ok('Super admin permissions retrieved successfully', [
                'permissions' => $permissions,
                'context' => 'super_admin',
            ]);
        }

        // Store owners get permissions based on their subscription/plan
        $result = $this->featureService->getUserPermissionsWithFeatures($user);

        // Filter to only store-context permissions
        $storePermissions = collect($result['permissions'])->filter(function ($permission) {
            return $permission->context === 'store';
        });

        return $this->ok('User permissions and features retrieved successfully', [
            'permissions' => $storePermissions->values(),
            'features' => FeatureResource::collection($result['features']),
            'context' => 'store',
        ]);
    }

    /**
     * Get user's permissions for a specific store.
     */
    public function storePermissions(Request $request, Store $store): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        $user = $request->user();

        // Check if user has access to this store
        if (! $this->featureService->userHasAccessToStore($user, $store)) {
            return $this->forbidden('You do not have access to this store');
        }

        $result = $this->featureService->getUserPermissionsForStoreWithFeatures($user, $store);

        // Filter to only store-context permissions
        $storePermissions = collect($result['permissions'])->filter(function ($permission) {
            return $permission->context === 'store';
        });

        return $this->ok('Store permissions and features retrieved successfully', [
            'permissions' => $storePermissions->values(),
            'features' => FeatureResource::collection($result['features']),
            'context' => 'store',
        ]);
    }
}
