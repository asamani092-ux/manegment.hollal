<?php

namespace App\Console\Commands;

use App\Services\BudgetService;
use Illuminate\Console\Command;

/**
 * 04-B6 — daily budget threshold sweep (80% / 100%).
 */
class CheckBudgetThresholds extends Command
{
    protected $signature = 'budgets:check-thresholds';

    protected $description = 'Notify finance and project managers about budgets at or over the 80% / 100% thresholds';

    public function handle(BudgetService $budgets): int
    {
        $alerted = $budgets->fireThresholdAlerts();

        foreach ($alerted as $row) {
            $this->line("project #{$row['project_id']} — {$row['percent']}% (tier {$row['tier']}%)");
        }

        $this->info(count($alerted).' budget threshold alert(s) sent.');

        return self::SUCCESS;
    }
}
