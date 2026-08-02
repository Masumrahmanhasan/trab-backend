<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\PlansStatus;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            'default' => [
                'name' => PlansStatus::defaultPlanKey(),
                'slug' => 'default',
                'description' => 'Default plan for all users will be assigned automatically.',
                'price' => 0,
                'billing_cycle' => BillingCycle::MONTHLY->value,
                'status' => PlansStatus::ACTIVE->value,
                'trial_days' => 0,
                'is_default' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
