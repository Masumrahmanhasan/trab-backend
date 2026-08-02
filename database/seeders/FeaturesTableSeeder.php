<?php

namespace Database\Seeders;

use App\Enums\FeatureStatus;
use App\Models\Feature;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class FeaturesTableSeeder extends Seeder
{
    /**
     * Materialise every feature declared in config/permissions.php.
     *
     * Each feature optionally references a permission key that gates it inside
     * the application. Adding a feature after launch is a config entry, then
     * `php artisan features:sync` upserts it here.
     */
    public function run(): void
    {
        $now = CarbonImmutable::now();
        $rows = [];

        foreach (config('permissions.features', []) as $feature) {
            $rows[] = [
                'name' => $feature['name'],
                'key' => $feature['key'],
                'description' => $feature['description'] ?? null,
                'permission_key' => $feature['permission_key'] ?? null,
                'context' => $feature['context'] ?? 'store',
                'status' => FeatureStatus::ACTIVE->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        Feature::query()->upsert(
            $rows,
            uniqueBy: ['key'],
            update: ['name', 'description', 'permission_key', 'context', 'status', 'updated_at']
        );
    }
}
