<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\PlansStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 99),
            'billing_cycle' => BillingCycle::MONTHLY->value,
            'status' => PlansStatus::ACTIVE->value,
            'trial_days' => 14,
            'max_stores' => 1,
            'max_staff_per_store' => 5,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state([
            'is_default' => true,
            'price' => 0,
            'slug' => 'default',
        ]);
    }
}
