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
     * @throws Throwable
     */
    public function run(): void
    {
        DB::transaction(function () {
            $superAdmin = Role::query()->create([
                'name' => 'Super Admin',
                'key' => 'super-admin'
            ]);

            $superAdmin->syncPermissions(Permission::all());
        });
    }
}
