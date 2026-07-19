<?php

namespace App\Console\Commands;

use App\Services\RecurringTaskService;
use Illuminate\Console\Command;

/**
 * 02-B3 — daily generation of due recurring task instances.
 */
class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring';

    protected $description = 'Generate task instances for recurring templates due today';

    public function handle(RecurringTaskService $service): int
    {
        $created = $service->generateDue();

        $this->info('Generated '.count($created).' recurring task instance(s).');

        return self::SUCCESS;
    }
}
