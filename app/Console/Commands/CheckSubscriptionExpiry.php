<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Services\PermissionCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionExpiry extends Command
{
    protected $signature = 'subscriptions:check-expiry';
    protected $description = 'Check and handle expired subscriptions';

    public function __construct(
        protected PermissionCacheService $cacheService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking for expired subscriptions...');

        $expiredSubscriptions = Subscription::query()
            ->where('status', '!=', 'expired')
            ->where('status', '!=', 'cancelled')
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
                $oldStatus = $subscription->status;
                $subscription->update(['status' => 'expired']);

                // Revoke store-specific permissions
                if ($subscription->store) {
                    $subscription->store->owner->permissions()
                        ->wherePivot('team_id', $subscription->store->id)
                        ->detach();

                    // Clear cache for the user
                    $this->cacheService->clearUserPermissions(
                        $subscription->user_id,
                        $subscription->store->id
                    );
                }

                Log::info('Subscription expired', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'store_id' => $subscription->store_id,
                    'old_status' => $oldStatus,
                ]);

                $count++;
            });
        }

        $this->info("Processed {$count} expired subscriptions.");

        return Command::SUCCESS;
    }
}
