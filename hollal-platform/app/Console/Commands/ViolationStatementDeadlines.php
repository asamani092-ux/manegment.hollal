<?php

namespace App\Console\Commands;

use App\Services\ViolationService;
use Illuminate\Console\Command;

class ViolationStatementDeadlines extends Command
{
    protected $signature = 'violations:statement-deadlines';

    protected $description = 'Move unanswered violation statements past their deadline to pending decision';

    public function handle(ViolationService $service): int
    {
        $moved = $service->processDeadlines();
        $this->info('moved '.$moved);

        return self::SUCCESS;
    }
}