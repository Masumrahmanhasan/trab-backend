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
        foreach (config('permissions.resources') as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => ucfirst($action).' '.ucfirst($resource),
                    'key' => "$action"."-"."$resource",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (config('permissions.extra') as $key => $extraPermission) {
            $permissions[] = [
                'name' => $extraPermission,
                'key' => $key,
                'created_at' => $now,
            ];
        }

        Permission::query()->upsert($permissions, uniqueBy: ['key'], update: ['name', 'created_at', 'updated_at']);
    }
}
