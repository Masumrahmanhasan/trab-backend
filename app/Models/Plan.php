<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 * @property-read float $price
 * @property-read string $billing_cycle
 * @property-read bool $is_active
 * @property-read int $max_stores
 * @property-read int $max_staff_per_store
 *
 * @method static active()
 * @method static default()
 */
#[Fillable(['name', 'slug', 'description', 'price', 'billing_cycle', 'is_active', 'max_stores', 'max_staff_per_store', 'trial_days', 'is_default'])]
class Plan extends Model
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope a query to only include active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to find the default plan.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true)->active();
    }
}
