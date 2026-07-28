<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 * @property-read int $owner_id
 * @property-read int|null $plan_id
 */
#[Fillable(['name', 'slug', 'description', 'owner_id', 'plan_id'])]
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

    public function staff(): Collection
    {
        return User::query()
            ->where(function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->wherePivot('team_id', $this->id);
                })->orWhere('id', $this->owner_id);
            })
            ->get();
    }

    public function getAvailablePermissions(): Collection
    {
        if (! $this->plan) {
            return collect();
        }

        return $this->plan->features->map(function ($feature) {
            $permission = $feature->getPermission();
            // Only return store-context permissions
            if ($permission && $permission->context === 'store') {
                return $permission;
            }
            return null;
        })->filter();
    }

    public function getFeatures(): Collection
    {
        return $this->plan ? $this->plan->features : collect();
    }
}
