<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansTableSeeder extends Seeder
{
    /**
     * Upsert every plan declared in config/permissions.php and link the
     * features each plan grants (with trial availability per feature).
     *
     * Idempotent: re-running re-syncs plan/feature links, which is how new
     * features propagate to plans after launch via `features:sync`.
     *
     * @throws \Throwable
     */
    public function run(): void
    {
        DB::transaction(function () {
            foreach (config('permissions.plans', []) as $definition) {
                $plan = Plan::query()->updateOrCreate(
                    ['slug' => $definition['slug']],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'] ?? null,
                        'price' => $definition['price'],
                        'billing_cycle' => $definition['billing_cycle'],
                        'status' => $definition['status'],
                        'trial_days' => $definition['trial_days'] ?? 0,
                        'max_stores' => $definition['max_stores'] ?? 1,
                        'max_staff_per_store' => $definition['max_staff_per_store'] ?? 0,
                        'is_default' => $definition['is_default'] ?? false,
                    ]
                );

                $this->syncPlanFeatures($plan, $definition['features'] ?? []);

                if ($plan->is_default) {
                    Plan::query()
                        ->whereKeyNot($plan->id)
                        ->update(['is_default' => false]);
                }
            }
        });
    }

    /**
     * Link the plan to its features, recording whether each feature is
     * available during the trial period.
     */
    protected function syncPlanFeatures(Plan $plan, array $features): void
    {
        if ($features === []) {
            $plan->features()->sync([]);

            return;
        }

        $pivot = Feature::query()
            ->whereIn('key', array_keys($features))
            ->pluck('id', 'key')
            ->mapWithKeys(fn (int $id, string $key) => [
                $id => ['is_trial_allowed' => (bool) $features[$key]],
            ])
            ->all();

        $plan->features()->sync($pivot);
    }
}
