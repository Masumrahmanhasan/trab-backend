<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AssignRoleRequest;
use App\Http\Requests\SuperAdmin\ManageSubscriptionRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Http\Resources\FeatureResource;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Contracts\SubscriptionServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    use ApiResponse;

    protected SubscriptionServiceInterface $subscriptionService;

    public function __construct(SubscriptionServiceInterface $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Get system statistics and overview.
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_stores' => Store::count(),
            'total_subscriptions' => Subscription::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'trial_subscriptions' => Subscription::where('status', 'trialing')->count(),
            'total_revenue' => Subscription::where('status', 'active')->sum('plan.price'),
            'plans_count' => Plan::count(),
            'roles_count' => Role::count(),
            'permissions_count' => Permission::count(),
        ];

        return $this->ok('System statistics retrieved successfully', $stats);
    }

    /**
     * Get all users with optional filtering.
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with(['roles', 'permissions']);

        // Filter by search term
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('key', $request->input('role'));
            });
        }

        // Filter by subscription status
        if ($request->has('subscription_status')) {
            $query->whereHas('subscriptions', function ($q) use ($request) {
                $q->where('status', $request->input('subscription_status'));
            });
        }

        $users = $query->latest()->paginate($request->input('per_page', 15));

        return $this->ok('Users retrieved successfully', [
            'data' => UserResource::collection($users->items()),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific user details.
     */
    public function showUser(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions', 'subscriptions.plan', 'stores']);

        return $this->ok('User details retrieved successfully', new UserResource($user));
    }

    /**
     * Update user information.
     */
    public function updateUser(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $user->update($validated);

        return $this->ok('User updated successfully', new UserResource($user->load('roles', 'permissions')));
    }

    /**
     * Delete user.
     */
    public function deleteUser(User $user): JsonResponse
    {
        // Prevent deletion of super admin
        if ($user->hasRole('super-admin')) {
            return $this->error('Cannot delete super admin user', 403);
        }

        $user->delete();

        return $this->ok('User deleted successfully');
    }

    /**
     * Assign role to user.
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::where('key', $validated['role'])->firstOrFail();

        $user->assignRole($role, $validated['team_id'] ?? null);

        return $this->ok('Role assigned successfully', new UserResource($user->load('roles')));
    }

    /**
     * Remove role from user.
     */
    public function removeRole(Request $request, User $user, string $roleKey): JsonResponse
    {
        $role = Role::where('key', $roleKey)->firstOrFail();

        // Prevent removing super admin role
        if ($role->key === 'super-admin') {
            return $this->error('Cannot remove super admin role', 403);
        }

        $user->removeRole($role, $request->input('team_id') ?? null);

        return $this->ok('Role removed successfully', new UserResource($user->load('roles')));
    }

    /**
     * Get all roles.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return $this->ok('Roles retrieved successfully', RoleResource::collection($roles));
    }

    /**
     * Get all permissions.
     */
    public function permissions(): JsonResponse
    {
        // Only return super admin permissions for super admin context
        $permissions = Permission::superAdmin()->get();

        return $this->ok('Super admin permissions retrieved successfully', PermissionResource::collection($permissions));
    }

    /**
     * Get all plans.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::with('features')->get();

        return $this->ok('Plans retrieved successfully', PlanResource::collection($plans));
    }

    /**
     * Get all features.
     */
    public function features(): JsonResponse
    {
        $features = Feature::with('plans')->get();

        return $this->ok('Features retrieved successfully', FeatureResource::collection($features));
    }

    /**
     * Get all subscriptions with filtering.
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $query = Subscription::with(['user', 'plan', 'store']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by plan
        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        $subscriptions = $query->latest()->paginate($request->input('per_page', 15));

        return $this->ok('Subscriptions retrieved successfully', [
            'data' => SubscriptionResource::collection($subscriptions->items()),
            'pagination' => [
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
            ],
        ]);
    }

    /**
     * Manage user subscription (upgrade/downgrade/cancel).
     */
    public function manageSubscription(ManageSubscriptionRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $subscription = $this->subscriptionService->getActiveSubscription($user);

        if (! $subscription) {
            return $this->error('User has no active subscription', 404);
        }

        match ($validated['action']) {
            'upgrade', 'downgrade' => $this->changeSubscriptionPlan($subscription, $validated['plan_id']),
            'cancel' => $this->subscriptionService->cancelSubscription($subscription),
            'resume' => $this->subscriptionService->resumeSubscription($subscription),
            'renew' => $this->subscriptionService->renewSubscription($subscription),
            default => throw new \InvalidArgumentException('Invalid action'),
        };

        return $this->ok('Subscription managed successfully', new SubscriptionResource($subscription->fresh()->load(['user', 'plan', 'store'])));
    }

    /**
     * Get all stores.
     */
    public function stores(Request $request): JsonResponse
    {
        $query = Store::with(['owner', 'plan']);

        // Filter by owner
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        // Filter by plan
        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        // Filter by search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $stores = $query->latest()->paginate($request->input('per_page', 15));

        return $this->ok('Stores retrieved successfully', [
            'data' => StoreResource::collection($stores->items()),
            'pagination' => [
                'total' => $stores->total(),
                'per_page' => $stores->perPage(),
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
            ],
        ]);
    }

    /**
     * Get specific store details.
     */
    public function showStore(Store $store): JsonResponse
    {
        $store->load(['owner', 'plan', 'subscriptions']);

        return $this->ok('Store details retrieved successfully', new StoreResource($store));
    }

    /**
     * Get system logs/activity.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        // This would typically use a logging package like spatie/laravel-activitylog
        // For now, return a placeholder response
        return $this->ok('Activity logs retrieved successfully', [
            'data' => [],
            'message' => 'Activity logging not implemented yet',
        ]);
    }

    /**
     * Get system health status.
     */
    public function systemHealth(): JsonResponse
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            'queue' => $this->checkQueueHealth(),
        ];

        $healthy = collect($health)->every(fn ($status) => $status === 'healthy');

        return $this->ok('System health retrieved successfully', [
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $health,
        ]);
    }

    /**
     * Check database health.
     */
    protected function checkDatabaseHealth(): string
    {
        try {
            DB::connection()->getPdo();

            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check cache health.
     */
    protected function checkCacheHealth(): string
    {
        try {
            \Cache::put('health_check', 'ok', 10);

            return \Cache::get('health_check') === 'ok' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check storage health.
     */
    protected function checkStorageHealth(): string
    {
        try {
            return is_writable(storage_path()) ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check queue health.
     */
    protected function checkQueueHealth(): string
    {
        try {
            return 'healthy'; // Placeholder - would check queue workers
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Change subscription plan.
     */
    protected function changeSubscriptionPlan(Subscription $subscription, int $planId): void
    {
        $newPlan = Plan::findOrFail($planId);
        $this->subscriptionService->changePlan($subscription, $newPlan);
    }
}
