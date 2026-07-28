<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class PermissionCacheService
{
    protected int $cacheTtl = 3600; // 1 hour

    public function getUserPermissions(int $userId, ?int $teamId = null): Collection
    {
        $cacheKey = $this->getUserPermissionsCacheKey($userId, $teamId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($userId, $teamId) {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return collect();
            }

            return $user->permissionsInTeam($teamId);
        });
    }

    public function getUserRoles(int $userId, ?int $teamId = null): Collection
    {
        $cacheKey = $this->getUserRolesCacheKey($userId, $teamId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($userId, $teamId) {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return collect();
            }

            return $user->rolesInTeam($teamId);
        });
    }

    public function getRolePermissions(int $roleId): Collection
    {
        $cacheKey = $this->getRolePermissionsCacheKey($roleId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($roleId) {
            $role = \App\Models\Role::find($roleId);
            if (!$role) {
                return collect();
            }

            return $role->permissions;
        });
    }

    public function clearUserPermissions(int $userId, ?int $teamId = null): void
    {
        Cache::forget($this->getUserPermissionsCacheKey($userId, $teamId));
        Cache::forget($this->getUserRolesCacheKey($userId, $teamId));
    }

    public function clearRolePermissions(int $roleId): void
    {
        Cache::forget($this->getRolePermissionsCacheKey($roleId));
    }

    public function clearAllUserCache(int $userId): void
    {
        // Clear all team-specific caches for user
        $user = \App\Models\User::find($userId);
        if ($user) {
            $teamIds = $user->roles()->pluck('team_id')->filter()->unique();
            foreach ($teamIds as $teamId) {
                $this->clearUserPermissions($userId, $teamId);
            }
        }

        // Clear global cache
        $this->clearUserPermissions($userId, null);
    }

    protected function getUserPermissionsCacheKey(int $userId, ?int $teamId = null): string
    {
        return "user_permissions:{$userId}" . ($teamId ? ":team:{$teamId}" : ":global");
    }

    protected function getUserRolesCacheKey(int $userId, ?int $teamId = null): string
    {
        return "user_roles:{$userId}" . ($teamId ? ":team:{$teamId}" : ":global");
    }

    protected function getRolePermissionsCacheKey(int $roleId): string
    {
        return "role_permissions:{$roleId}";
    }
}
