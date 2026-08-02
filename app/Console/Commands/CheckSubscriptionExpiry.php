<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Contracts\SubscriptionServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionExpiry extends Command
{
    protected $signature = 'subscriptions:check-expiry';

    protected $description = 'Expire subscriptions whose paid period or trial has ended';

    public function __construct(
        protected SubscriptionServiceInterface $subscriptionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking for expired subscriptions...');

        $expiredSubscriptions = Subscription::query()
            ->whereNotIn('status', ['expired', 'cancelled'])
            ->where(function ($query) {
                $query->where('ends_at', '<', now())
                    ->orWhere(function ($query) {
                        $query->where('status', 'trialing')
                            ->where('trial_ends_at', '<', now());
                    });
            })
            ->get();

        $count = 0;

        foreach ($expiredSubscriptions as $subscription) {
            DB::transaction(function () use ($subscription, &$count) {
                $this->subscriptionService->expireSubscription($subscription);

                Log::info('Subscription expired', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'store_id' => $subscription->store_id,
                ]);

                $count++;
            });
        }

        $this->info("Processed {$count} expired subscriptions.");

        return Command::SUCCESS;
    }
}
