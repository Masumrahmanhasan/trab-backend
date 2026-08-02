<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignStaffRequest;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\StoreUpdateRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;

    protected StoreOnboardingServiceInterface $onboardingService;

    protected ActivityLogger $activityLogger;

    public function __construct(
        StoreOnboardingServiceInterface $onboardingService,
        ActivityLogger $activityLogger
    ) {
        $this->onboardingService = $onboardingService;
        $this->activityLogger = $activityLogger;
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
        $this->authorize('view', $store);

        return $this->ok('Store retrieved successfully', new StoreResource($store->load('plan')));
    }

    /**
     * Update the specified store.
     */
    public function update(StoreUpdateRequest $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

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
        $this->authorize('delete', $store);

        $store->delete();

        return $this->ok('Store deleted successfully');
    }

    /**
     * Get store staff members.
     */
    public function staff(Request $request, Store $store): JsonResponse
    {
        $this->authorize('viewStaff', $store);

        $staff = $store->staff();

        return $this->ok('Store staff retrieved successfully', UserResource::collection($staff));
    }

    /**
     * Get available permissions for the store based on plan.
     */
    public function availablePermissions(Request $request, Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $permissions = $store->getAvailablePermissions();

        return $this->ok('Available permissions retrieved successfully', PermissionResource::collection($permissions));
    }

    /**
     * Assign staff member to store with role and permissions.
     */
    public function assignStaff(AssignStaffRequest $request, Store $store): JsonResponse
    {
        $this->authorize('assignStaff', $store);

        $validated = $request->validated();
        $staffUser = User::where('email', $validated['email'])->first();

        if (! $staffUser) {
            return $this->notFound('User not found');
        }

        // Validate staff limit
        $this->validateStaffLimit($store);

        // Validate permissions are available in plan
        if (isset($validated['permissions']) && count($validated['permissions']) > 0) {
            $this->onboardingService->validatePermissionsForStore($store, $validated['permissions']);
        }

        DB::transaction(function () use ($store, $staffUser, $validated, $request) {
            // Assign role to user in store context
            $staffUser->assignRole($validated['role_id'], $store->id);

            // Assign specific permissions if provided
            if (isset($validated['permissions']) && count($validated['permissions']) > 0) {
                $staffUser->syncPermissions($validated['permissions'], $store->id);
            }

            // Log activity
            $this->activityLogger->logStaffAssignment(
                $request->user(),
                $staffUser,
                $store->id,
                $validated,
                $request
            );
        });

        return $this->ok('Staff assigned successfully', new UserResource($staffUser->load('roles')));
    }

    /**
     * Remove staff member from store.
     */
    public function removeStaff(Request $request, Store $store, User $staff): JsonResponse
    {
        $this->authorize('removeStaff', $store);

        if ($staff->id === $store->owner_id) {
            return $this->forbidden('Cannot remove store owner');
        }

        DB::transaction(function () use ($store, $staff, $request) {
            // Remove all roles in store context
            $staff->roles()->wherePivot('team_id', $store->id)->detach();

            // Remove all permissions in store context
            $staff->permissions()->wherePivot('team_id', $store->id)->detach();

            // Log activity
            $this->activityLogger->logStaffRemoval(
                $request->user(),
                $staff,
                $store->id,
                $request
            );
        });

        return $this->ok('Staff removed successfully');
    }

    /**
     * Update staff permissions.
     */
    public function updateStaffPermissions(Request $request, Store $store, User $staff): JsonResponse
    {
        $this->authorize('updateStaffPermissions', $store);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        // Validate permissions are available in plan
        $this->onboardingService->validatePermissionsForStore($store, $validated['permissions']);

        DB::transaction(function () use ($store, $staff, $validated) {
            $staff->syncPermissions($validated['permissions'], $store->id);
        });

        return $this->ok('Staff permissions updated successfully');
    }

    /**
     * Validate staff limit based on plan.
     */
    protected function validateStaffLimit(Store $store): void
    {
        if (! $store->plan) {
            throw new \InvalidArgumentException('Store has no plan assigned');
        }

        if ($store->plan->max_staff_per_store > 0) {
            $currentStaffCount = $store->staff()->count() - 1; // Exclude owner
            if ($currentStaffCount >= $store->plan->max_staff_per_store) {
                throw new \InvalidArgumentException(
                    "Staff limit reached. Your plan allows {$store->plan->max_staff_per_store} staff members per store."
                );
            }
        }
    }
}
