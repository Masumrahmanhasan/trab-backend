<?php

namespace Database\Seeders;

use App\Models\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Materialise every permission declared in config/permissions.php.
     *
     * Permission keys are namespaced `<context>.<resource>.<action>`. Adding a
     * permission for a future feature is one line in config — this seeder then
     * upserts it (matched on the unique `key` column) the next time it runs.
     */
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');
        $now = CarbonImmutable::now();
        $rows = [];

        foreach (config('permissions.contexts', []) as $context => $definition) {
            foreach ($definition['resources'] ?? [] as $resource => $actions) {
                foreach ($actions as $action) {
                    $rows[] = $this->row($context, "{$context}.{$resource}.{$action}", $now, $guard);
                }
            }

            foreach ($definition['extra'] ?? [] as $key => $name) {
                $rows[] = $this->row($context, $key, $now, $guard, $name);
            }
        }

        if ($rows === []) {
            return;
        }

        Permission::query()->upsert(
            $rows,
            uniqueBy: ['key'],
            update: ['name', 'context', 'guard_name', 'updated_at']
        );
    }

    protected function row(string $context, string $key, CarbonImmutable $now, string $guard, ?string $name = null): array
    {
        return [
            'name' => $name ?? $this->humanize($key),
            'key' => $key,
            'context' => $context,
            'guard_name' => $guard,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function humanize(string $key): string
    {
        $parts = explode('.', $key);
        $action = array_pop($parts);
        $resource = implode(' ', $parts);

        return Str::headline($action).' '.Str::headline($resource);
    }
}
