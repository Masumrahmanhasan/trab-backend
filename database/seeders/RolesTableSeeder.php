<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = Role::query()->create([
            'name' => 'super-admin',
            'key' => 'superadmin'
        ]);

        $superAdmin->syncPermissions(Permission::all());
    }
}
