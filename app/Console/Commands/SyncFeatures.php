<?php

namespace App\Console\Commands;

use App\Services\PermissionSyncService;
use Database\Seeders\FeaturesTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\PlansTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Console\Command;

class SyncFeatures extends Command
{
    protected $signature = 'features:sync';

    protected $description = 'Materialise new permissions, features and plans from config and propagate them to existing subscribers';

    public function handle(PermissionSyncService $permissionSync): int
    {
        $this->info('Syncing permissions...');
        $this->callSilently(PermissionsTableSeeder::class);

        $this->info('Syncing roles...');
        $this->callSilently(RolesTableSeeder::class);

        $this->info('Syncing features...');
        $this->callSilently(FeaturesTableSeeder::class);

        $this->info('Syncing plans...');
        $this->callSilently(PlansTableSeeder::class);

        $this->info('Propagating features to existing subscribers...');
        $permissionSync->resyncAllCurrentSubscribers();

        $this->info('Feature sync complete.');

        return Command::SUCCESS;
    }
}
