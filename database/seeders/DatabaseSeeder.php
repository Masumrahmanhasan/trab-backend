<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionsTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(PlanFeatureSeeder::class);
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@gmail.com',
        ]);

        $superAdmin->assignRole('super-admin');
    }
}
