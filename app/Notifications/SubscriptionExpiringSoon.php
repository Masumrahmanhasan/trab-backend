<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Subscription $subscription
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $daysUntilExpiry = now()->diffInDays($this->subscription->ends_at);

        return (new MailMessage)
            ->subject('Your Subscription is Expiring Soon')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your subscription will expire in {$daysUntilExpiry} day(s).")
            ->line("Plan: {$this->subscription->plan->name}")
            ->action('Renew Subscription', url('/subscriptions/' . $this->subscription->id . '/renew'))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'ends_at' => $this->subscription->ends_at,
            'days_until_expiry' => now()->diffInDays($this->subscription->ends_at),
        ];
    }
}
