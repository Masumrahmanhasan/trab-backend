<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionCreateRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Contracts\SubscriptionServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use ApiResponse;

    protected SubscriptionServiceInterface $subscriptionService;

    public function __construct(SubscriptionServiceInterface $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of the user's subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->subscriptionService->getUserSubscriptions($request->user());

        return $this->ok('Subscriptions retrieved successfully', SubscriptionResource::collection($subscriptions));
    }

    /**
     * Display the user's active subscription.
     */
    public function active(Request $request): JsonResponse
    {
        $activeSubscription = $this->subscriptionService->getActiveSubscription($request->user());

        if (! $activeSubscription) {
            return $this->error('No active subscription found', 404);
        }

        return $this->ok('Active subscription retrieved successfully', new SubscriptionResource($activeSubscription));
    }

    /**
     * Store a newly created subscription.
     */
    public function store(SubscriptionCreateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $plan = Plan::findOrFail($validated['plan_id']);

        if (! $this->subscriptionService->canSubscribe($user, $plan)) {
            return $this->error('Cannot subscribe to this plan', 400);
        }

        $newSubscription = $this->subscriptionService->createSubscription(
            $user,
            $plan,
            $validated['store_id'] ?? null,
            $validated
        );

        return $this->created('Subscription created successfully', new SubscriptionResource($newSubscription));
    }

    /**
     * Display the specified subscription.
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id && ! $request->user()->hasRole('super-admin')) {
            return $this->forbidden('You do not have access to this subscription');
        }

        return $this->ok('Subscription retrieved successfully', new SubscriptionResource($subscription->load(['plan', 'store'])));
    }

    /**
     * Cancel the specified subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id && ! $request->user()->hasRole('super-admin')) {
            return $this->forbidden('You do not have permission to cancel this subscription');
        }

        $subscription = $this->subscriptionService->cancelSubscription($subscription);

        return $this->ok('Subscription cancelled successfully', new SubscriptionResource($subscription));
    }

    /**
     * Resume the specified subscription.
     */
    public function resume(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id && ! $request->user()->hasRole('super-admin')) {
            return $this->forbidden('You do not have permission to resume this subscription');
        }

        $subscription = $this->subscriptionService->resumeSubscription($subscription);

        return $this->ok('Subscription resumed successfully', new SubscriptionResource($subscription));
    }

    /**
     * Change the subscription plan.
     */
    public function changePlan(SubscriptionUpdateRequest $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id && ! $request->user()->hasRole('super-admin')) {
            return $this->forbidden('You do not have permission to change this subscription');
        }

        $validated = $request->validated();
        $newPlan = Plan::findOrFail($validated['plan_id']);

        $subscription = $this->subscriptionService->changePlan($subscription, $newPlan);

        return $this->ok('Subscription plan changed successfully', new SubscriptionResource($subscription->load('plan')));
    }

    /**
     * Renew an expired subscription.
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id && ! $request->user()->hasRole('super-admin')) {
            return $this->forbidden('You do not have permission to renew this subscription');
        }

        $subscription = $this->subscriptionService->renewSubscription($subscription);

        return $this->ok('Subscription renewed successfully', new SubscriptionResource($subscription));
    }
}
