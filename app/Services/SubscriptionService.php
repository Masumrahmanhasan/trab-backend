<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Contracts\StoreOnboardingServiceInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SubscriptionService implements SubscriptionServiceInterface
{
    public function __construct(
        protected PermissionSyncService $permissionSync,
        protected StoreOnboardingServiceInterface $storeOnboarding,
    ) {}

    /**
     * Create a subscription for a brand-new user on the default plan, which
     * starts their trial period.
     *
     * @throws \RuntimeException when no default plan is configured
     */
    public function createSubscriptionWithAutoSetup(User $user, ?Store $store = null): Subscription
    {
        $defaultPlan = $this->getDefaultPlan();

        if (! $defaultPlan) {
            throw new \RuntimeException('No default plan is configured. Seed the database first.');
        }

        $subscription = $this->createSubscription($user, $defaultPlan, $store, [
            'starts_at' => now(),
        ]);

        Log::info('Auto subscription created for new user', [
            'user_id' => $user->id,
            'plan_id' => $defaultPlan->id,
            'store_id' => $store?->id,
        ]);

        return $subscription;
    }

    public function getDefaultPlan(): ?Plan
    {
        return Plan::query()->default()->active()->first();
    }

    /**
     * Create a new subscription, starting a trial if the plan has one, and
     * materialise the plan's permissions for the user.
     *
     * @throws InvalidArgumentException when the user cannot subscribe
     */
    public function createSubscription(User $user, Plan $plan, ?Store $store = null, array $data = []): Subscription
    {
        if (! $this->canSubscribe($user, $plan)) {
            throw new InvalidArgumentException('User cannot subscribe to this plan');
        }

        $startDate = Carbon::parse($data['starts_at'] ?? now());
        $trialDays = (int) ($data['trial_days'] ?? $plan->trial_days ?? 0);
        $trialEndsAt = $trialDays > 0 ? $startDate->copy()->addDays($trialDays) : null;
        $endsAt = $trialEndsAt ? null : $this->calculateEndDate($plan, $startDate);

        return DB::transaction(function () use ($user, $plan, $store, $data, $startDate, $trialEndsAt, $endsAt) {
            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'store_id' => $store?->id,
                'status' => $trialEndsAt ? SubscriptionStatus::TRIALING->value : SubscriptionStatus::ACTIVE->value,
                'starts_at' => $startDate,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialEndsAt,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_gateway_id' => $data['payment_gateway_id'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($store) {
                $store->update(['plan_id' => $plan->id]);
                $this->storeOnboarding->assignPlanPermissionsToOwner($store);
            }

            $this->permissionSync->syncGlobalPlanPermissions(
                $user,
                $plan,
                $trialEndsAt !== null
            );

            Log::info('Subscription created', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'store_id' => $store?->id,
            ]);

            return $subscription;
        });
    }

    public function cancelSubscription(Subscription $subscription): Subscription
    {
        if (! $subscription->isActive() && ! $subscription->isOnTrial()) {
            throw new InvalidArgumentException('Only active or trialing subscriptions can be cancelled');
        }

        DB::transaction(function () use ($subscription) {
            $subscription->cancel();

            // Tear down plan-derived access: global permissions and, for the
            // owner, store-scoped roles/permissions across owned stores.
            $this->permissionSync->revokeGlobalPlanPermissions($subscription->user);

            foreach ($subscription->user->ownedStores as $store) {
                $this->permissionSync->revokeStoreAccess($subscription->user, $store->id);
            }

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    public function resumeSubscription(Subscription $subscription): Subscription
    {
        if (! $subscription->isCancelled()) {
            throw new InvalidArgumentException('Only cancelled subscriptions can be resumed');
        }

        DB::transaction(function () use ($subscription) {
            $subscription->resume();

            $this->restorePlanAccess($subscription);

            Log::info('Subscription resumed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        if (! $subscription->isCurrent()) {
            throw new InvalidArgumentException('Only current subscriptions can change plans');
        }

        if ($subscription->plan_id === $newPlan->id) {
            return $subscription;
        }

        DB::transaction(function () use ($subscription, $newPlan) {
            $oldPlanId = $subscription->plan_id;
            $subscription->update([
                'plan_id' => $newPlan->id,
                'ends_at' => $this->calculateEndDate($newPlan, $subscription->starts_at ?? now()),
            ]);

            if ($subscription->store) {
                $subscription->store->update(['plan_id' => $newPlan->id]);
            }

            // Re-derive permissions from the new plan.
            $this->permissionSync->syncGlobalPlanPermissions(
                $subscription->user,
                $newPlan,
                $subscription->isOnTrial()
            );

            // The account plan governs every store the user owns; mirror it.
            foreach ($subscription->user->ownedStores as $store) {
                $store->update(['plan_id' => $newPlan->id]);
                $this->storeOnboarding->assignPlanPermissionsToOwner($store);
            }

            Log::info('Subscription plan changed', [
                'subscription_id' => $subscription->id,
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlan->id,
            ]);
        });

        return $subscription->fresh();
    }

    public function calculateEndDate(Plan $plan, DateTime $startDate): DateTime
    {
        $cycle = BillingCycle::tryFrom($plan->billing_cycle) ?? BillingCycle::MONTHLY;

        return Carbon::parse($startDate)->addMonths($cycle->months());
    }

    public function renewSubscription(Subscription $subscription): Subscription
    {
        if (! $subscription->isExpired()) {
            throw new InvalidArgumentException('Only expired subscriptions can be renewed');
        }

        DB::transaction(function () use ($subscription) {
            $plan = $subscription->plan;
            $startDate = Carbon::now();

            $subscription->update([
                'status' => SubscriptionStatus::ACTIVE->value,
                'starts_at' => $startDate,
                'ends_at' => $this->calculateEndDate($plan, $startDate),
                'trial_ends_at' => null,
                'cancelled_at' => null,
            ]);

            $this->restorePlanAccess($subscription);

            Log::info('Subscription renewed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    /**
     * Mark a subscription expired and revoke its access grants.
     */
    public function expireSubscription(Subscription $subscription): Subscription
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update(['status' => SubscriptionStatus::EXPIRED->value]);

            $this->permissionSync->revokeGlobalPlanPermissions($subscription->user);

            foreach ($subscription->user->ownedStores as $store) {
                $this->permissionSync->revokeStoreAccess($subscription->user, $store->id);
            }

            Log::info('Subscription expired', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);
        });

        return $subscription->fresh();
    }

    public function getActiveSubscription(User $user): ?Subscription
    {
        return $user->activeSubscription()->first();
    }

    public function getUserSubscriptions(User $user): Collection
    {
        return $user->subscriptions()->with(['plan', 'store'])->latest()->get();
    }

    public function canSubscribe(User $user, Plan $plan): bool
    {
        if (! $plan->isActive()) {
            return false;
        }

        if ($this->hasActiveSubscription($user)) {
            return false;
        }

        if ($plan->max_stores > 0) {
            $currentStoreCount = $user->ownedStores()->count();

            if ($currentStoreCount >= $plan->max_stores) {
                return false;
            }
        }

        return true;
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $user->activeSubscription()->exists();
    }

    /**
     * Re-grant the access a subscription provides (global permissions + owner
     * store grants). Used by resume/renew.
     */
    protected function restorePlanAccess(Subscription $subscription): void
    {
        $this->permissionSync->syncGlobalPlanPermissions(
            $subscription->user,
            $subscription->plan,
            $subscription->isOnTrial()
        );

        foreach ($subscription->user->ownedStores as $store) {
            $this->storeOnboarding->assignPlanPermissionsToOwner($store);
        }
    }
}
