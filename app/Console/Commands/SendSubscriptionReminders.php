<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';
    protected $description = 'Send subscription expiry reminders';

    public function handle(): int
    {
        $this->info('Sending subscription expiry reminders...');

        // Remind 7 days before expiry
        $sevenDaysExpiring = Subscription::query()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(7))
            ->whereDoesntHave('notifications', function ($query) {
                $query->where('type', 'App\Notifications\SubscriptionExpiringSoon')
                    ->where('created_at', '>', now()->subDays(7));
            })
            ->get();

        foreach ($sevenDaysExpiring as $subscription) {
            try {
                $subscription->user->notify(new \App\Notifications\SubscriptionExpiringSoon($subscription));

                Log::info('Subscription reminder sent', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'days_until_expiry' => now()->diffInDays($subscription->ends_at),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send subscription reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent reminders for {$sevenDaysExpiring->count()} subscriptions expiring soon.");

        return Command::SUCCESS;
    }
}
