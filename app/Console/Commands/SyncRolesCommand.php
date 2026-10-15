<?php

namespace App\Console\Commands;

use App\Services\Access\RoleSynchronizer;
use Illuminate\Console\Command;

class SyncRolesCommand extends Command
{
    protected $signature = 'bms:roles:sync';

    protected $description = 'Create or update the built-in roles and their permissions';

    public function handle(RoleSynchronizer $synchronizer): int
    {
        $count = $synchronizer->sync();
        $this->components->info("Synchronized {$count} built-in roles.");

        return self::SUCCESS;
    }
}
