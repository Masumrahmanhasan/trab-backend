<?php

namespace Database\Seeders;

use App\Enums\Roles;
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
            $roles = Roles::cases();
            foreach ($roles as $role) {
                $role = Role::create(['name' => $role->value, 'key' => $role->value]);
                if ($role === Roles::ADMIN->value) {
                    $permissions = Permission::all();
                    $role->permissions()->sync($permissions->pluck('id')->toArray());
                }
            }
        });
    }
}
