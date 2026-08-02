<?php

namespace App\Models;

use App\Enums\PermissionContext;
use App\Models\Concerns\HasPermissions;
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
class Role extends Model
{
    use HasPermissions;

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            'model_has_roles',
            'role_id',
            'model_id',
        )->withPivot('team_id');
    }

    public function usersInTeam(int $teamId): MorphToMany
    {
        return $this->users()->wherePivot('team_id', $teamId);
    }

    /**
     * Scope a query to find a role by its key.
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    /**
     * Scope to platform (SaaS-owner) roles only.
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('context', PermissionContext::PLATFORM->value);
    }

    /**
     * Scope to store (tenant) roles only.
     */
    public function scopeStore(Builder $query): Builder
    {
        return $query->where('context', PermissionContext::STORE->value);
    }
}
