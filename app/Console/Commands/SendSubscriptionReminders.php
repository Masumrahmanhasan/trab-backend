<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';

    protected $description = 'Send subscription expiry reminders';

    public function handle(): int
    {
        $this->info('Sending subscription expiry reminders...');

        // Remind 7 days before expiry, at most once per week per user.
        $expiring = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(7))
            ->whereHas('user', function ($query) {
                $query->whereDoesntHave('notifications', function ($query) {
                    $query->where('type', SubscriptionExpiringSoon::class)
                        ->where('created_at', '>', now()->subDays(7));
                });
            })
            ->with('user')
            ->get();

        foreach ($expiring as $subscription) {
            $subscription->user->notify(new SubscriptionExpiringSoon($subscription));

            Log::info('Subscription reminder sent', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'days_until_expiry' => now()->diffInDays($subscription->ends_at),
            ]);
        }

        $this->info("Sent reminders for {$expiring->count()} subscriptions expiring soon.");

        return Command::SUCCESS;
    }
}
