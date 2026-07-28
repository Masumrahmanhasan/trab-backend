<?php

namespace Database\Seeders;

use App\Models\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

use function ucfirst;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [];
        $now = CarbonImmutable::now();

        // Seed super admin permissions
        foreach (config('permissions.super_admin.resources') as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => ucfirst($action).' '.ucfirst($resource),
                    'key' => "$action".'-'."$resource",
                    'context' => 'super_admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (config('permissions.super_admin.extra') as $key => $extraPermission) {
            $permissions[] = [
                'name' => $extraPermission,
                'key' => $key,
                'context' => 'super_admin',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Seed store permissions
        foreach (config('permissions.store.resources') as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => ucfirst($action).' '.ucfirst($resource),
                    'key' => "$action".'-'."$resource",
                    'context' => 'store',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (config('permissions.store.extra') as $key => $extraPermission) {
            $permissions[] = [
                'name' => $extraPermission,
                'key' => $key,
                'context' => 'store',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Permission::query()->upsert($permissions, uniqueBy: ['key'], update: ['name', 'context', 'created_at', 'updated_at']);
    }
}
