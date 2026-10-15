<?php

namespace Database\Seeders;

use App\Services\Access\RoleSynchronizer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(RoleSynchronizer $roles): void
    {
        $roles->sync();
        $this->call(LookupSeeder::class);

        if (app()->environment('local')) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
