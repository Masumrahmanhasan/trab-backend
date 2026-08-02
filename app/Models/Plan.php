<?php

namespace App\Models;

use App\Enums\PlansStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
 * @property-read int $trial_days
 * @property-read bool $is_default
 *
 * @method static Builder|Plan default()
 * @method static Builder|Plan active()
 */
#[Fillable(['name', 'slug', 'description', 'price', 'billing_cycle', 'trial_days', 'is_default'])]
class Plan extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature');
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
}
