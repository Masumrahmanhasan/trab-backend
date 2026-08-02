<?php

namespace App\Models;

use App\Enums\PermissionContext;
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
 * @property-read string $guard_name
 * @property-read null|CarbonImmutable $created_at
 * @property-read null|CarbonImmutable $updated_at
 */
#[Fillable(['name', 'key', 'context', 'guard_name'])]
class Permission extends Model
{
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

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'features', 'permission_key', 'key');
    }

    /**
     * Platform (SaaS-owner panel) permissions only.
     */
    public function scopeSuperAdmin(Builder $query): Builder
    {
        return $query->where('context', PermissionContext::PLATFORM->value);
    }

    /**
     * Store (merchant/tenant) permissions only.
     */
    public function scopeStore(Builder $query): Builder
    {
        return $query->where('context', PermissionContext::STORE->value);
    }

    /**
     * Whether this permission belongs to the merchant (tenant) namespace.
     */
    public function isStoreScoped(): bool
    {
        return $this->context === PermissionContext::STORE->value;
    }
}
