<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Authentication required',
                'status' => 401,
                'data' => [],
            ], 401);
        }

        // Check if user has an active subscription
        if (! $user->activeSubscription) {
            return response()->json([
                'message' => 'Active subscription required',
                'status' => 403,
                'data' => [
                    'reason' => 'no_active_subscription',
                    'requires_subscription' => true,
                ],
            ], 403);
        }

        // Check if subscription is actually active
        $subscription = $user->activeSubscription;
        if (! $subscription->isActive() && ! $subscription->isOnTrial()) {
            return response()->json([
                'message' => 'Subscription is not active',
                'status' => 403,
                'data' => [
                    'reason' => 'subscription_inactive',
                    'subscription_status' => $subscription->status,
                    'requires_subscription' => true,
                ],
            ], 403);
        }

        return $next($request);
    }
}
