<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read int $plan_id
 * @property-read int $store_id
 * @property-read string $status
 * @property-read Carbon|null $starts_at
 * @property-read Carbon|null $ends_at
 * @property-read Carbon|null $trial_ends_at
 * @property-read Carbon|null $cancelled_at
 * @property-read string|null $payment_method
 * @property-read string|null $payment_gateway_id
 * @property-read array|null $metadata
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Plan $plan
 * @property-read Store $store
 *
 * @method static current()
 * @method static active()
 * @method static cancelled()
 * @method static expired()
 * @method static trialing()
 */
#[Fillable(['user_id', 'plan_id', 'store_id', 'status', 'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at', 'payment_method', 'payment_gateway_id', 'metadata',])]
class Subscription extends Model
{
    use SoftDeletes;

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan for the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the store for the subscription.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->active()->orWhere(function ($query) {
                $query->where('status', 'trialing')
                    ->where('trial_ends_at', '>', now());
            });
        });
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Scope a query to only include cancelled subscriptions.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($query) {
                $query->where('ends_at', '<', now())
                    ->whereNotNull('ends_at');
            });
    }

    /**
     * Scope a query to only include trialing subscriptions.
     */
    public function scopeTrialing(Builder $query): Builder
    {
        return $query->where('status', 'trialing')
            ->where('trial_ends_at', '>', now());
    }

    /**
     * Scope a query to only include subscriptions for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include subscriptions for a specific store.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function isCurrent()
    {
        return $this->isActive() || $this->isOnTrial();
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
            $this->starts_at &&
            $this->starts_at->isPast() &&
            ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Check if subscription is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trialing' &&
            $this->trial_ends_at &&
            $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Resume the subscription.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
        ]);
    }

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
