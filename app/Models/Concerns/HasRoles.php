<?php

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property-read Collection<int, Role> $roles
 */
trait HasRoles
{
    public function hasAnyRole(array $roles): bool
    {
        return collect($roles)->some(fn($role) => $this->hasRole($role));
    }

    public function hasRole(string|Role $role): bool
    {
        $key = $role instanceof Role ? $role->key : $role;
        return $this->roles->contains('key', $key);
    }

    public function assignRole(string|Role $role): static
    {
        $this->roles()->syncWithoutDetaching(
            $this->resolveRole($role)
        );

        return $this;
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        );
    }

    public function resolveRole(string|Role $role): Role
    {
        return $role instanceof Role
            ? $role
            : Role::where('key', $role)->firstOrFail();
    }

    public function removeRole(string|Role $role): static
    {
        $this->roles()->detach(
            $this->resolveRole($role)
        );
        return $this;
    }

    public function syncRoles(array|SupportCollection $roles): static
    {
        $this->roles()->sync(
            collect($roles)->map(fn($role) => $this->resolveRole($role)->id)
        );
        return $this;
    }
}