<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters:
     *   1. Permissions must exist before roles reference them.
     *   2. Roles must exist before users are assigned them.
     *   3. Features/plans must exist before users are subscribed to plans.
     */
    public function run(): void
    {
        $this->call(PermissionsTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(FeaturesTableSeeder::class);
        $this->call(PlansTableSeeder::class);
        $this->call(UsersTableSeeder::class);
    }
}
