<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionCacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

/**
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, Role> $roles
 */
trait HasPermissions
{
    use HasTeamContext;

    public function assignPermissionTo(string|Permission $permission, ?int $teamId = null): static
    {
        $teamId = $teamId ?? $this->getTeamId();

        $this->permissions()->syncWithoutDetaching([
            $this->resolvePermission($permission)->id => ['team_id' => $teamId],
        ]);

        // Log activity
        if (method_exists($this, 'logPermissionGrant')) {
            $this->logPermissionGrant($permission, $teamId);
        }

        // Clear cache
        $this->clearPermissionCache($teamId);

        return $this;
    }

    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            Permission::class,
            'model',
            'model_has_permissions',
            'model_id',
            'permission_id',
        )->withPivot('team_id');
    }

    public function resolvePermission(string|Permission $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        // Try to find by ID first, then by key
        return Permission::where('id', $permission)
            ->orWhere('key', $permission)
            ->firstOrFail();
    }

    public function syncPermissions(array|SupportCollection $permissions, ?int $teamId = null): static
    {
        $teamId = $teamId ?? $this->getTeamId();

        $syncData = collect($permissions)
            ->mapWithKeys(function ($permission) use ($teamId) {
                $pivotData = [];
                if ($teamId !== null) {
                    $pivotData['team_id'] = $teamId;
                }

                return [$this->resolvePermission($permission)->id => $pivotData];
            });

        $this->permissions()->sync($syncData);

        // Clear cache
        $this->clearPermissionCache($teamId);

        return $this;
    }

    public function revokePermissionTo(string|Permission $permission, ?int $teamId = null): static
    {
        $teamId = $teamId ?? $this->getTeamId();

        $query = $this->permissions()->where($this->resolvePermission($permission)->getKeyName(), $this->resolvePermission($permission)->id);

        if ($teamId !== null) {
            $query->wherePivot('team_id', $teamId);
        }

        $query->detach();

        // Clear cache
        $this->clearPermissionCache($teamId);

        return $this;
    }

    public function hasAnyPermissions(array $permissions, ?int $teamId = null): bool
    {
        return collect($permissions)->some(fn ($permission) => $this->hasPermissionTo($permission, $teamId));
    }

    public function hasPermissionTo(string|Permission $permission, ?int $teamId = null): bool
    {
        $key = $permission instanceof Permission ? $permission->key : $permission;
        $teamId = $teamId ?? $this->getTeamId();

        $permissionsQuery = $this->permissions();

        if ($teamId !== null) {
            $permissionsQuery->wherePivot('team_id', $teamId);
        }

        if ($permissionsQuery->get()->contains('key', $key)) {
            return true;
        }

        if (method_exists($this, 'roles')) {
            $rolesQuery = $this->roles();

            if ($teamId !== null) {
                $rolesQuery->wherePivot('team_id', $teamId);
            }

            return $rolesQuery->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->contains('key', $key);
        }

        return false;
    }

    public function permissionsInTeam(?int $teamId = null): Collection
    {
        $teamId = $teamId ?? $this->getTeamId();

        if ($teamId === null) {
            return $this->permissions;
        }

        return $this->permissions()->wherePivot('team_id', $teamId)->get();
    }

    protected function clearPermissionCache(?int $teamId = null): void
    {
        if (method_exists($this, 'getKey') && method_exists($this, 'clearTeamId')) {
            $cacheService = app(PermissionCacheService::class);
            $cacheService->clearUserPermissions($this->getKey(), $teamId);
        }
    }
}
