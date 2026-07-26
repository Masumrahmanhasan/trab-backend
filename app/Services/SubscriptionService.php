<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Contracts\SubscriptionServiceInterface;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionService implements SubscriptionServiceInterface
{
    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription): Subscription
    {
        if (!$subscription->isActive() && !$subscription->isOnTrial()) {
            throw new InvalidArgumentException('Only active or trialing subscriptions can be cancelled');
        }

        DB::transaction(function () use ($subscription) {
            $subscription->cancel();

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resumeSubscription(Subscription $subscription): Subscription
    {
        if (!$subscription->isCancelled()) {
            throw new InvalidArgumentException('Only cancelled subscriptions can be resumed');
        }

        DB::transaction(function () use ($subscription) {
            $subscription->resume();

            Log::info('Subscription resumed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    /**
     * Upgrade or change subscription plan.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        if (!$subscription->isActive()) {
            throw new InvalidArgumentException('Only active subscriptions can change plans');
        }

        DB::transaction(function () use ($subscription, $newPlan) {
            $oldPlanId = $subscription->plan_id;
            $subscription->update(['plan_id' => $newPlan->id]);

            // Update store plan if subscription is linked to a store
            if ($subscription->store) {
                $subscription->store->update(['plan_id' => $newPlan->id]);
            }

            // Recalculate end date based on new plan
            $subscription->update([
                'ends_at' => $this->calculateEndDate($newPlan, $subscription->starts_at),
            ]);

            Log::info('Subscription plan changed', [
                'subscription_id' => $subscription->id,
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlan->id,
            ]);
        });

        return $subscription->fresh();
    }

    /**
     * Calculate subscription end date based on plan billing cycle.
     */
    public function calculateEndDate(Plan $plan, DateTime $startDate): DateTime
    {
        return match ($plan->billing_cycle) {
            'quarterly' => Carbon::parse($startDate)->addMonths(3),
            'yearly' => Carbon::parse($startDate)->addYear(),
            default => Carbon::parse($startDate)->addMonth(),
        };
    }

    /**
     * Renew an expired subscription.
     */
    public function renewSubscription(Subscription $subscription): Subscription
    {
        if (!$subscription->isExpired()) {
            throw new InvalidArgumentException('Only expired subscriptions can be renewed');
        }

        DB::transaction(function () use ($subscription) {
            $plan = $subscription->plan;
            $startDate = Carbon::now();
            $endDate = $this->calculateEndDate($plan, $startDate);

            $subscription->update([
                'status' => 'active',
                'starts_at' => $startDate,
                'ends_at' => $endDate,
                'trial_ends_at' => null,
                'cancelled_at' => null,
            ]);

            Log::info('Subscription renewed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    /**
     * Get user's active subscription.
     */
    public function getActiveSubscription(User $user): ?Subscription
    {
        return $user->activeSubscription()->first();
    }

    /**
     * Get all user subscriptions.
     */
    public function getUserSubscriptions(User $user): Collection
    {
        return $user->subscriptions()->with(['plan', 'store'])->latest()->get();
    }

    /**
     * Create a new subscription with automatic setup for new users.
     */
    public function createSubscriptionWithAutoSetup(User $user, ?Store $store = null): Subscription
    {
        $defaultPlan = $this->getDefaultPlan();

        if (!$defaultPlan) {
            throw new RuntimeException('No default plan configured');
        }

        // Create subscription with trial
        $subscription = $this->createSubscription(
            $user,
            $defaultPlan,
            $store,
            [
                'trial_days' => $defaultPlan->trial_days,
                'starts_at' => now(),
            ]
        );

        // Assign default role and permissions at user level
        $this->assignDefaultRoleAndPermissions($user, $defaultPlan);

        Log::info('Auto subscription created for new user', [
            'user_id' => $user->id,
            'plan_id' => $defaultPlan->id,
            'store_id' => $store?->id,
        ]);

        return $subscription;
    }

    /**
     * Get the default plan.
     */
    public function getDefaultPlan(): ?Plan
    {
        return Plan::default()->first();
    }

    /**
     * Create a new subscription.
     */
    public function createSubscription(User $user, Plan $plan, ?Store $store = null, array $data = []): Subscription
    {
        if (!$this->canSubscribe($user, $plan)) {
            throw new InvalidArgumentException('User cannot subscribe to this plan');
        }

        $startDate = Carbon::parse($data['starts_at'] ?? now());
        $trialDays = $data['trial_days'] ?? $plan->trial_days ?? 0;
        $trialEndsAt = $trialDays > 0 ? $startDate->copy()->addDays($trialDays) : null;
        $endsAt = $trialDays > 0 ? null : $this->calculateEndDate($plan, $startDate);

        return DB::transaction(function () use ($user, $plan, $store, $data, $startDate, $trialEndsAt, $endsAt) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'store_id' => $store?->id,
                'status' => $trialEndsAt ? 'trialing' : 'active',
                'starts_at' => $startDate,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialEndsAt,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_gateway_id' => $data['payment_gateway_id'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            // If subscription is for a store, update the store's plan
            if ($store) {
                $store->update(['plan_id' => $plan->id]);
            }

            Log::info('Subscription created', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'store_id' => $store?->id,
            ]);

            return $subscription;
        });
    }

    /**
     * Validate if user can subscribe to plan.
     */
    public function canSubscribe(User $user, Plan $plan): bool
    {
        // Check if plan is active
        if (!$plan->is_active) {
            return false;
        }

        // Check if user already has an active subscription
        if ($this->hasActiveSubscription($user)) {
            // Allow if it's a plan change, not a new subscription
            return false;
        }

        // Check store limits if plan has max_stores restriction
        if ($plan->max_stores > 0) {
            $currentStoreCount = $user->ownedStores()->count();
            if ($currentStoreCount >= $plan->max_stores) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has active subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        return $user->activeSubscription()->exists();
    }

    /**
     * Assign default role and permissions to user.
     */
    protected function assignDefaultRoleAndPermissions(User $user, Plan $plan): void
    {
        // Assign owner role at user level
        $ownerRole = Role::where('key', 'owner')->first();
        if ($ownerRole) {
            $user->assignRole($ownerRole);
        }

        // Assign plan-based permissions at user level
        $plan->load('features');
        $permissions = $plan->features->map(function ($feature) {
            return $feature->getPermission();
        })->filter();

        $permissionIds = $permissions->pluck('id')->toArray();

        if (count($permissionIds) > 0) {
            $user->permissions()->syncWithoutDetaching($permissionIds);
        }

        Log::info('Default role and permissions assigned to user', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'permissions_count' => count($permissionIds),
        ]);
    }
}
