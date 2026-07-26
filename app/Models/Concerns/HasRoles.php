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
    use HasTeamContext;

    public function hasAnyRole(array $roles, ?int $teamId = null): bool
    {
        return collect($roles)->some(fn ($role) => $this->hasRole($role, $teamId));
    }

    public function hasRole(string|Role $role, ?int $teamId = null): bool
    {
        $key = $role instanceof Role ? $role->key : $role;
        $teamId = $teamId ?? $this->getTeamId();

        $rolesQuery = $this->roles();

        if ($teamId !== null) {
            $rolesQuery->wherePivot('team_id', $teamId);
        }

        return $rolesQuery->get()->contains('key', $key);
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        )->withPivot('team_id');
    }

    public function assignRole(string|Role $role, ?int $teamId = null): static
    {
        $teamId = $teamId ?? $this->getTeamId();

        $this->roles()->syncWithoutDetaching([
            $this->resolveRole($role)->id => ['team_id' => $teamId],
        ]);

        return $this;
    }

    public function resolveRole(string|Role $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        // Try to find by ID first, then by key
        return Role::where('id', $role)
            ->orWhere('key', $role)
            ->firstOrFail();
    }

    public function removeRole(string|Role $role, ?int $teamId = null): static
    {
        $teamId = $teamId ?? $this->getTeamId();

        $query = $this->roles()->where($this->resolveRole($role)->getKeyName(), $this->resolveRole($role)->id);

        if ($teamId !== null) {
            $query->wherePivot('team_id', $teamId);
        }

        $query->detach();

        return $this;
    }

    public function syncRoles(array|SupportCollection $roles, ?int $teamId = null): static
    {
        $teamId = $teamId ?? $this->getTeamId();

        $syncData = collect($roles)->mapWithKeys(function ($role) use ($teamId) {
            return [$this->resolveRole($role)->id => ['team_id' => $teamId]];
        });

        $this->roles()->sync($syncData);

        return $this;
    }

    public function rolesInTeam(?int $teamId = null): Collection
    {
        $teamId = $teamId ?? $this->getTeamId();

        if ($teamId === null) {
            return $this->roles;
        }

        return $this->roles()->wherePivot('team_id', $teamId)->get();
    }
}
