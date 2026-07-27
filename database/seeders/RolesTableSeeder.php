<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @throws Throwable
     */
    public function run(): void
    {
        DB::transaction(function () {
            $superAdmin = Role::query()->updateOrCreate(
                ['key' => 'super-admin'],
                [
                    'name' => 'Super Admin',
                    'key' => 'super-admin',
                ]
            );

            $allPermissions = Permission::all();
            if ($allPermissions->isNotEmpty()) {
                $superAdmin->syncPermissions($allPermissions->pluck('id')->toArray());
            }

            // Create owner role for store owners
            $owner = Role::query()->updateOrCreate(
                ['key' => 'owner'],
                [
                    'name' => 'Owner',
                    'key' => 'owner',
                ]
            );

            // Owner gets all permissions by default (can be customised per plan)
            if ($allPermissions->isNotEmpty()) {
                $owner->syncPermissions($allPermissions->pluck('id')->toArray());
            }
        });
    }
}
