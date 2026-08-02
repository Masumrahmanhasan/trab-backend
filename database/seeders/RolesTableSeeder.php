<?php

namespace Database\Seeders;

use App\Enums\PermissionContext;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Upsert every built-in role declared in config/permissions.php and sync
     * its permission set.
     *
     * This is idempotent: re-running simply re-syncs the permission sets,
     * which is exactly what `features:sync` relies on to keep Super Admin and
     * Store Owner up to date with newly added permissions.
     *
     * @throws \Throwable
     */
    public function run(): void
    {
        DB::transaction(function () {
            $guard = config('permissions.guard', 'web');

            foreach (config('permissions.roles', []) as $key => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $definition['name'],
                        'context' => $definition['context'],
                        'guard_name' => $guard,
                    ]
                );

                $role->permissions()->sync($this->resolvePermissions($role->key, $definition['permissions']));
            }
        });
    }

    /**
     * Resolve a role's declared permission list into permission IDs.
     */
    protected function resolvePermissions(string $roleKey, string|array $declaration): array
    {
        if (is_array($declaration)) {
            return $declaration === []
                ? []
                : Permission::query()->whereIn('key', $declaration)->pluck('id')->all();
        }

        return match ($declaration) {
            'platform' => Permission::query()
                ->where('context', PermissionContext::PLATFORM->value)
                ->pluck('id')
                ->all(),
            'store' => Permission::query()
                ->where('context', PermissionContext::STORE->value)
                ->pluck('id')
                ->all(),
            default => throw new \InvalidArgumentException(
                "Unknown permission declaration '{$declaration}' for role '{$roleKey}'"
            ),
        };
    }
}
