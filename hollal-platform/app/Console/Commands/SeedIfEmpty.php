<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Full demo seed only on an empty database.
 * Time: O(1) check, O(n) seed once | Space: O(1)
 */
class SeedIfEmpty extends Command
{
    protected $signature = 'db:seed-if-empty';

    protected $description = 'Run the demo seed only when no users exist yet';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('Database already has users; skipping demo seed.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--force' => true]);

        return self::SUCCESS;
    }
}
