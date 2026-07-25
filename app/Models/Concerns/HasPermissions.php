<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, Role> $roles
 */
trait HasPermissions
{
    /**
     * Determine if the user has the given permission.
     * @param  string|Permission  $permission
     * @return bool
     */
    public function hasPermissionTo(string|Permission $permission): bool
    {
        $key = $permission instanceof Permission ? $permission->key : $permission;

        if ($this->permissions->contains('key', $key)) {
            return true;
        }

        if (method_exists($this, 'roles')) {
            return $this->roles->loadMissing('permissions')
                ->pluck('permissions')
                ->flatten()
                ->contains('key', $key);
        }

        return false;
    }

    /**
     * Assign the given permission to the user.
     * @param  string|Permission  $permission
     * @return $this
     * @throws ModelNotFoundException
     */
    public function assignPermissionTo(string|Permission $permission): static
    {
        $this->permissions()->syncWithoutDetaching(
            $this->resolvePermission($permission)
        );

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
        );
    }

    public function resolvePermission(string|Permission $permission): Permission
    {
        return $permission instanceof Permission
            ? $permission
            : Permission::where('key', $permission)->firstOrFail();
    }
}