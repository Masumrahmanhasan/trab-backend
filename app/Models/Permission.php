<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $key
 * @property-read string $context
 * @property-read null|CarbonImmutable $created_at
 * @property-read null|CarbonImmutable $updated_at
 */
#[Fillable(['name', 'key', 'context'])]
class Permission extends Model
{

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            'model_has_permissions',
            'permission_id',
            'model_id',
        );
    }

    /**
     * Scope a query to find a permission by its key.
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * Scope a query to only include super admin permissions.
     */
    public function scopeSuperAdmin(Builder $query): Builder
    {
        return $query->where('context', 'super_admin');
    }

    /**
     * Scope a query to only include store permissions.
     */
    public function scopeStore(Builder $query): Builder
    {
        return $query->where('context', 'store');
    }
}
