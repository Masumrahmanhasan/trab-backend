<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\StoreUpdateRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    use ApiResponse;

    protected StoreOnboardingServiceInterface $onboardingService;

    public function __construct(StoreOnboardingServiceInterface $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Display a listing of the user's stores.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        $stores = $request->user()->ownedStores()->with('plan')->get();

        return $this->ok('Stores retrieved successfully', StoreResource::collection($stores));
    }

    /**
     * Store a newly created store in storage.
     */
    public function store(StoreStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $store = $this->onboardingService->createStoreWithPlan(
            $validated,
            $request->user(),
            $validated['plan_id']
        );

        return $this->created('Store created successfully', new StoreResource($store->load('plan')));
    }

    /**
     * Display the specified store.
     */
    public function show(Request $request, Store $store): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        // Check if user owns the store or has access
        if ($store->owner_id !== $request->user()->id) {
            return $this->forbidden('You do not have access to this store');
        }

        return $this->ok('Store retrieved successfully', new StoreResource($store->load('plan')));
    }

    /**
     * Update the specified store.
     */
    public function update(StoreUpdateRequest $request, Store $store): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['plan_id'])) {
            $this->onboardingService->updateStorePlan($store, $validated['plan_id']);
            unset($validated['plan_id']);
        }

        $store->update($validated);

        return $this->ok('Store updated successfully', new StoreResource($store->load('plan')));
    }

    /**
     * Remove the specified store.
     */
    public function destroy(Request $request, Store $store): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        if ($store->owner_id !== $request->user()->id) {
            return $this->forbidden('You do not have permission to delete this store');
        }

        $store->delete();

        return $this->ok('Store deleted successfully');
    }

    /**
     * Get store staff members.
     */
    public function staff(Request $request, Store $store): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        if ($store->owner_id !== $request->user()->id) {
            return $this->forbidden('You do not have access to this store');
        }

        $staff = $store->staff();

        return $this->ok('Store staff retrieved successfully', UserResource::collection($staff));
    }

    /**
     * Get available permissions for the store based on plan.
     */
    public function availablePermissions(Request $request, Store $store): JsonResponse
    {
        if (! $request->user()) {
            return $this->forbidden('Authentication required');
        }

        if ($store->owner_id !== $request->user()->id) {
            return $this->forbidden('You do not have access to this store');
        }

        $permissions = $store->getAvailablePermissions();

        return $this->ok('Available permissions retrieved successfully', PermissionResource::collection($permissions));
    }
}
