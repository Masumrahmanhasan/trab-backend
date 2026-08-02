<?php

namespace App\Models;

use App\Enums\PlansStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
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
 * @property-read string $status
 * @property-read bool $is_default
 * @property-read int $trial_days
 * @property-read int $max_stores
 * @property-read int $max_staff_per_store
 * @property-read bool $is_active
 *
 * @method static Builder|Plan default()
 * @method static Builder|Plan active()
 */
#[Fillable(['name', 'slug', 'description', 'price', 'billing_cycle', 'status', 'trial_days', 'max_stores', 'max_staff_per_store', 'is_default'])]
class Plan extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<int, Feature>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature')
            ->withPivot('is_trial_allowed')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    #[Scope]
    protected function default(Builder $query): void
    {
        $query->where('is_default', true);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', PlansStatus::ACTIVE->value);
    }

    public function isActive(): bool
    {
        return $this->status === PlansStatus::ACTIVE->value;
    }

    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    /**
     * Read-only compatibility accessor so existing resources/code can keep
     * using `$plan->is_active` without a column rename.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }
}
