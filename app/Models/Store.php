<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A Store is a tenant: the isolated workspace a user (owner) manages, with its
 * own staff, roles and store-scoped permissions.
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 * @property-read int $owner_id
 * @property-read int|null $plan_id
 * @property-read string $status
 */
#[Fillable(['name', 'slug', 'description', 'owner_id', 'plan_id', 'status'])]
class Store extends Model
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === StoreStatus::ACTIVE->value;
    }

    /**
     * Users who belong to this store: anyone holding a store role scoped to
     * this store, plus the owner.
     */
    public function staff(): Collection
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereHas('roles', function (Builder $q) {
                    $q->wherePivot('team_id', $this->id);
                })->orWhere('id', $this->owner_id);
            })
            ->get();
    }

    /**
     * Store-context permissions the current plan grants to this store.
     */
    public function getAvailablePermissions(): Collection
    {
        if (! $this->plan) {
            return collect();
        }

        return $this->plan->features
            ->map(fn (Feature $feature) => $feature->getPermission())
            ->filter()
            ->filter(fn (Permission $permission) => $permission->isStoreScoped())
            ->values();
    }

    /**
     * All features granted by the store's plan.
     */
    public function getFeatures(): Collection
    {
        return $this->plan ? $this->plan->features : collect();
    }

    /**
     * The maximum number of staff (excluding the owner) this store may have.
     */
    public function staffLimit(): int
    {
        return $this->plan?->max_staff_per_store ?? 0;
    }

    /**
     * The maximum number of stores the owner may open with the current plan.
     */
    public function storeLimit(): int
    {
        return $this->plan?->max_stores ?? 1;
    }
}
