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

            // Super admin gets only super admin permissions
            $superAdminPermissions = Permission::superAdmin()->get();
            if ($superAdminPermissions->isNotEmpty()) {
                $superAdmin->syncPermissions($superAdminPermissions->pluck('id')->toArray());
            }

            // Create owner role for store owners
            $owner = Role::query()->updateOrCreate(
                ['key' => 'owner'],
                [
                    'name' => 'Owner',
                    'key' => 'owner',
                ]
            );

            // Owner gets store permissions (will be filtered by plan features)
            $storePermissions = Permission::store()->get();
            if ($storePermissions->isNotEmpty()) {
                $owner->syncPermissions($storePermissions->pluck('id')->toArray());
            }

            // Create staff role for store staff
            $staff = Role::query()->updateOrCreate(
                ['key' => 'staff'],
                [
                    'name' => 'Staff',
                    'key' => 'staff',
                ]
            );

            // Staff gets limited store permissions (can be customized per staff member)
            $limitedPermissions = Permission::store()
                ->whereIn('key', ['view-products', 'view-orders', 'view-customers', 'view-inventory'])
                ->get();
            if ($limitedPermissions->isNotEmpty()) {
                $staff->syncPermissions($limitedPermissions->pluck('id')->toArray());
            }
        });
    }
}
