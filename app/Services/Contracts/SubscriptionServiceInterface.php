<?php

namespace App\Services\Contracts;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use DateTime;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionServiceInterface
{
    /**
     * Create a new subscription.
     */
    public function createSubscription(User $user, Plan $plan, ?Store $store = null, array $data = []): Subscription;

    /**
     * Create a subscription with automatic setup for new users.
     */
    public function createSubscriptionWithAutoSetup(User $user, ?Store $store = null): Subscription;

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription): Subscription;

    /**
     * Resume a cancelled subscription.
     */
    public function resumeSubscription(Subscription $subscription): Subscription;

    /**
     * Upgrade or change subscription plan.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription;

    /**
     * Renew an expired subscription.
     */
    public function renewSubscription(Subscription $subscription): Subscription;

    /**
     * Check if user has active subscription.
     */
    public function hasActiveSubscription(User $user): bool;

    /**
     * Get user's active subscription.
     */
    public function getActiveSubscription(User $user): ?Subscription;

    /**
     * Get all user subscriptions.
     */
    public function getUserSubscriptions(User $user): Collection;

    /**
     * Validate if user can subscribe to plan.
     */
    public function canSubscribe(User $user, Plan $plan): bool;

    /**
     * Calculate subscription end date based on plan billing cycle.
     */
    public function calculateEndDate(Plan $plan, DateTime $startDate): DateTime;

    /**
     * Get the default plan.
     */
    public function getDefaultPlan(): ?Plan;
}
